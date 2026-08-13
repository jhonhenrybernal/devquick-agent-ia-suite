<?php

namespace App\Services\Dolibarr;

use App\Models\DolibarrConfiguration;
use DOMDocument;
use DOMXPath;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

class DolibarrApi
{
    /**
     * Validate Dolibarr credentials, request an API token and discover exposed operations.
     *
     * @return array{
     *     valid: bool,
     *     description: string,
     *     setup_url: string,
     *     discovered_apis: array<int, string>,
     *     important_apis: array<int, string>,
     *     discovered_api_count: int,
     *     important_api_count: int
     * }
     */
    public function inspectConfiguration(DolibarrConfiguration $configuration): array
    {
        try {
            $token = $this->accessToken($configuration);
        } catch (RuntimeException $exception) {
            return $this->failureResult($exception->getMessage());
        }

        try {
            $explorerResponse = $this->authorizedClient($configuration, $token)
                ->get('/api/index.php/explorer');
        } catch (ConnectionException $exception) {
            return $this->failureResult($this->describeFailure($exception));
        }

        if (! $explorerResponse->successful()) {
            return $this->failureResult($this->responseDescription($explorerResponse->status(), $explorerResponse->body()));
        }

        $discoveredApis = $this->discoverApis($explorerResponse->body());
        $importantApis = $this->importantApis($discoveredApis);

        return [
            'valid' => true,
            'description' => $discoveredApis !== []
                ? sprintf('Dolibarr respondio correctamente y se detectaron %d operaciones.', count($discoveredApis))
                : 'Dolibarr respondio correctamente.',
            'setup_url' => $this->setupUrl(),
            'discovered_apis' => $discoveredApis,
            'important_apis' => $importantApis,
            'discovered_api_count' => count($discoveredApis),
            'important_api_count' => count($importantApis),
        ];
    }

    /**
     * Retrieve the API token for the configured Dolibarr user.
     */
    public function accessToken(DolibarrConfiguration $configuration): string
    {
        if (blank($configuration->api_login) || blank($configuration->api_password) || blank($configuration->api_url)) {
            throw new RuntimeException('Guarda el login, la contrasena y la URL antes de continuar.');
        }

        $baseUrl = $this->apiBaseUrl($configuration);

        try {
            $tokenResponse = $this->httpClient($baseUrl)
                ->get('/api/index.php/login', [
                    'login' => $configuration->api_login,
                    'password' => $configuration->api_password,
                ]);
        } catch (ConnectionException $exception) {
            throw new RuntimeException($this->describeFailure($exception), 0, $exception);
        }

        if (! $tokenResponse->successful()) {
            throw new RuntimeException($this->loginFailureDescription($tokenResponse->status(), $tokenResponse->body()));
        }

        $token = $this->extractToken($tokenResponse);

        if (blank($token)) {
            throw new RuntimeException('Dolibarr no devolvio un token de acceso valido.');
        }

        return $token;
    }

    /**
     * Search customers using the connected Dolibarr instance.
     *
     * @return array{count: int, customers: array<int, array<string, mixed>>}
     */
    public function customers(DolibarrConfiguration $configuration, string $search = '', int $limit = 20): array
    {
        $token = $this->accessToken($configuration);
        $response = $this->authorizedClient($configuration, $token)
            ->get('/api/index.php/thirdparties', [
                'limit' => min(max($limit, 1), 100),
                'sortfield' => 't.rowid',
            ]);

        if (! $response->successful()) {
            throw new RuntimeException($this->responseDescription($response->status(), $response->body()));
        }

        $customers = [];

        foreach ($this->normalizeListPayload($response->json()) as $item) {
            if ($search !== '' && ! $this->matchesSearch($search, $item, [
                'name',
                'nom',
                'ref',
                'code_client',
                'city',
                'town',
            ])) {
                continue;
            }

            $customers[] = [
                'id' => $this->toInteger(data_get($item, 'id') ?? data_get($item, 'rowid')),
                'name' => (string) (data_get($item, 'name') ?? data_get($item, 'nom') ?? data_get($item, 'label') ?? ''),
                'reference' => (string) (data_get($item, 'ref') ?? data_get($item, 'code_client') ?? ''),
                'city' => data_get($item, 'city') ?? data_get($item, 'town'),
                'country' => data_get($item, 'country') ?? data_get($item, 'country_code'),
                'isCustomer' => $this->toBoolean(data_get($item, 'customer') ?? data_get($item, 'client')),
                'isSupplier' => $this->toBoolean(data_get($item, 'supplier')),
            ];

            if (count($customers) >= $limit) {
                break;
            }
        }

        return [
            'count' => count($customers),
            'customers' => $customers,
        ];
    }

    /**
     * Search products or services using the connected Dolibarr instance.
     *
     * @return array{count: int, products: array<int, array<string, mixed>>}
     */
    public function searchProducts(DolibarrConfiguration $configuration, string $search = '', int $limit = 20): array
    {
        $token = $this->accessToken($configuration);
        $response = $this->authorizedClient($configuration, $token)
            ->get('/api/index.php/products', [
                'limit' => min(max($limit, 1), 100),
                'sortfield' => 'p.rowid',
            ]);

        if (! $response->successful()) {
            throw new RuntimeException($this->responseDescription($response->status(), $response->body()));
        }

        $products = [];

        foreach ($this->normalizeListPayload($response->json()) as $item) {
            if ($search !== '' && ! $this->matchesSearch($search, $item, [
                'ref',
                'label',
                'description',
                'description_short',
            ])) {
                continue;
            }

            $products[] = [
                'id' => $this->toInteger(data_get($item, 'id') ?? data_get($item, 'rowid')),
                'ref' => (string) (data_get($item, 'ref') ?? ''),
                'label' => (string) (data_get($item, 'label') ?? data_get($item, 'description') ?? ''),
                'description' => data_get($item, 'description'),
                'price' => data_get($item, 'price'),
                'priceTtc' => data_get($item, 'price_ttc'),
                'type' => data_get($item, 'type'),
            ];

            if (count($products) >= $limit) {
                break;
            }
        }

        return [
            'count' => count($products),
            'products' => $products,
        ];
    }

    /**
     * List recent customer invoices from the connected Dolibarr instance.
     *
     * @return array{count: int, invoices: array<int, array<string, mixed>>}
     */
    public function invoices(DolibarrConfiguration $configuration, string $search = '', int $limit = 20): array
    {
        $token = $this->accessToken($configuration);
        $response = $this->authorizedClient($configuration, $token)
            ->get('/api/index.php/invoices', [
                'limit' => min(max($limit, 1), 100),
            ]);

        if (! $response->successful()) {
            throw new RuntimeException($this->responseDescription($response->status(), $response->body()));
        }

        $invoices = [];

        foreach ($this->normalizeListPayload($response->json()) as $item) {
            if ($search !== '' && ! $this->matchesSearch($search, $item, [
                'ref',
                'ref_client',
                'label',
                'socname',
                'customer_name',
                'thirdparty_name',
            ])) {
                continue;
            }

            $invoices[] = [
                'id' => $this->toInteger(data_get($item, 'id') ?? data_get($item, 'rowid')),
                'ref' => (string) (data_get($item, 'ref') ?? ''),
                'reference' => (string) (data_get($item, 'ref_client') ?? ''),
                'customerName' => (string) (data_get($item, 'socname') ?? data_get($item, 'customer_name') ?? data_get($item, 'thirdparty_name') ?? ''),
                'date' => data_get($item, 'date') ?? data_get($item, 'datef') ?? data_get($item, 'date_facture'),
                'totalTtc' => data_get($item, 'total_ttc') ?? data_get($item, 'totalTTC') ?? data_get($item, 'total'),
                'totalHt' => data_get($item, 'total_ht') ?? data_get($item, 'totalHT'),
                'status' => data_get($item, 'status') ?? data_get($item, 'statut'),
                'statusLabel' => data_get($item, 'status_label') ?? data_get($item, 'label_status'),
                'paid' => $this->toBoolean(data_get($item, 'paid') ?? data_get($item, 'paye')),
            ];

            if (count($invoices) >= $limit) {
                break;
            }
        }

        usort($invoices, static function (array $left, array $right): int {
            $leftDate = strtotime((string) ($left['date'] ?? '')) ?: 0;
            $rightDate = strtotime((string) ($right['date'] ?? '')) ?: 0;

            if ($leftDate !== $rightDate) {
                return $rightDate <=> $leftDate;
            }

            return (int) ($right['id'] ?? 0) <=> (int) ($left['id'] ?? 0);
        });

        return [
            'count' => count($invoices),
            'invoices' => $invoices,
        ];
    }

    /**
     * Get the details of a single invoice.
     *
     * @return array{invoice: array<string, mixed>, lines: array<int, array<string, mixed>>, line_count: int}
     */
    public function invoice(DolibarrConfiguration $configuration, int $invoiceId): array
    {
        $token = $this->accessToken($configuration);
        $response = $this->authorizedClient($configuration, $token)
            ->get(sprintf('/api/index.php/invoices/%d', $invoiceId));

        if (! $response->successful()) {
            throw new RuntimeException($this->responseDescription($response->status(), $response->body()));
        }

        $payload = $this->normalizeInvoicePayload($response->json());
        $lines = $this->normalizeInvoiceLines($payload);

        return [
            'invoice' => $this->normalizeInvoiceRecord($payload),
            'lines' => $lines,
            'line_count' => count($lines),
        ];
    }

    /**
     * Search invoices using the connected Dolibarr instance.
     *
     * @param  array{
     *     search?: string,
     *     status?: string,
     *     thirdparty_ids?: string,
     *     date_from?: string,
     *     date_to?: string,
     *     limit?: int,
     *     page_size?: int
     * }  $filters
     * @return array{count: int, invoices: array<int, array<string, mixed>>, filters: array<string, mixed>}
     */
    public function searchInvoices(DolibarrConfiguration $configuration, array $filters = []): array
    {
        $token = $this->accessToken($configuration);
        $search = trim((string) ($filters['search'] ?? ''));
        $statusFilter = $this->normalizeInvoiceStatusFilter((string) ($filters['status'] ?? ''));
        $thirdpartyIds = trim((string) ($filters['thirdparty_ids'] ?? ''));
        $dateFrom = $this->normalizeDateFilter((string) ($filters['date_from'] ?? ''));
        $dateTo = $this->normalizeDateFilter((string) ($filters['date_to'] ?? ''));
        $limit = max(1, min(100, (int) ($filters['limit'] ?? 20)));
        $pageSize = max(1, min(100, (int) ($filters['page_size'] ?? 100)));
        $sqlFilters = $this->buildInvoiceSqlFilters($dateFrom, $dateTo);

        $invoices = [];
        $page = 0;

        while (count($invoices) < $limit) {
            $query = array_filter([
                'sortfield' => 't.rowid',
                'sortorder' => 'DESC',
                'limit' => $pageSize,
                'page' => $page,
                'thirdparty_ids' => $thirdpartyIds !== '' ? $thirdpartyIds : null,
                'status' => $statusFilter,
                'sqlfilters' => $sqlFilters !== '' ? $sqlFilters : null,
            ], static fn (mixed $value): bool => $value !== null && $value !== '');

            $response = $this->authorizedClient($configuration, $token)
                ->get('/api/index.php/invoices', $query);

            if (! $response->successful()) {
                throw new RuntimeException($this->responseDescription($response->status(), $response->body()));
            }

            $pageInvoices = $this->normalizeListPayload($response->json());

            if ($pageInvoices === []) {
                break;
            }

            foreach ($pageInvoices as $item) {
                $invoice = $this->normalizeInvoiceRecord($item);

                if (! $this->invoiceMatchesSearch($invoice, $search)) {
                    continue;
                }

                $invoices[] = $invoice;

                if (count($invoices) >= $limit) {
                    break 2;
                }
            }

            if (count($pageInvoices) < $pageSize) {
                break;
            }

            $page++;
        }

        return [
            'count' => count($invoices),
            'invoices' => $invoices,
            'filters' => [
                'search' => $search !== '' ? $search : null,
                'status' => $statusFilter,
                'thirdparty_ids' => $thirdpartyIds !== '' ? $thirdpartyIds : null,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
            ],
        ];
    }

    /**
     * Update the state of an invoice.
     *
     * @param  array{status: string|int, close_code?: string, close_note?: string}  $data
     * @return array{updated: bool, invoice_id: int, status: int, status_label: string, close_code: string|null, close_note: string|null, invoice: array<string, mixed>, lines: array<int, array<string, mixed>>, line_count: int}
     */
    public function updateInvoice(DolibarrConfiguration $configuration, int $invoiceId, array $data): array
    {
        $token = $this->accessToken($configuration);
        $status = $this->normalizeInvoiceStatusCode($data['status'] ?? null);

        if ($status === null) {
            throw new RuntimeException('El status de la factura es obligatorio para actualizarla.');
        }

        $payload = array_filter([
            'id' => $invoiceId,
            'status' => $status,
            'close_code' => filled($data['close_code'] ?? null) ? (string) $data['close_code'] : null,
            'close_note' => filled($data['close_note'] ?? null) ? (string) $data['close_note'] : null,
        ], static fn (mixed $value): bool => $value !== null && $value !== '');

        $response = $this->authorizedClient($configuration, $token)
            ->put(sprintf('/api/index.php/invoices/%d', $invoiceId), $payload);

        if (! $response->successful()) {
            throw new RuntimeException($this->responseDescription($response->status(), $response->body()));
        }

        $invoice = $this->invoice($configuration, $invoiceId);

        return [
            'updated' => true,
            'invoice_id' => $invoiceId,
            'status' => $status,
            'status_label' => $this->invoiceStatusLabel($status),
            'close_code' => filled($data['close_code'] ?? null) ? (string) $data['close_code'] : null,
            'close_note' => filled($data['close_note'] ?? null) ? (string) $data['close_note'] : null,
            ...$invoice,
        ];
    }

    /**
     * Create a draft customer invoice and optionally add invoice lines.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function createInvoice(DolibarrConfiguration $configuration, array $data): array
    {
        $token = $this->accessToken($configuration);
        $customerId = $this->toInteger($data['customer_id'] ?? null);

        if ($customerId === null) {
            throw new RuntimeException('El customer_id es obligatorio para crear una factura.');
        }

        $invoiceDate = filled($data['invoice_date'] ?? null)
            ? (string) $data['invoice_date']
            : now()->toDateString();

        $payload = array_filter([
            'socid' => $customerId,
            'fk_soc' => $customerId,
            'type' => 0,
            'date' => $invoiceDate,
            'datefacture' => $invoiceDate,
            'ref_client' => filled($data['reference'] ?? null) ? (string) $data['reference'] : null,
            'note_private' => filled($data['note_private'] ?? null) ? (string) $data['note_private'] : null,
            'note_public' => filled($data['note_public'] ?? null) ? (string) $data['note_public'] : null,
        ], static fn (mixed $value): bool => $value !== null && $value !== '');

        $response = $this->authorizedClient($configuration, $token)
            ->post('/api/index.php/invoices', $payload);

        if (! $response->successful()) {
            throw new RuntimeException($this->responseDescription($response->status(), $response->body()));
        }

        $invoiceId = $this->extractIdentifier($response);

        if ($invoiceId === null) {
            throw new RuntimeException('Dolibarr no devolvio el ID de la factura creada.');
        }

        $lineItems = [];
        $lines = is_array($data['lines'] ?? null) ? $data['lines'] : [];

        foreach ($lines as $line) {
            if (! is_array($line)) {
                continue;
            }

            $linePayload = array_filter([
                'desc' => (string) ($line['description'] ?? ''),
                'qty' => $line['quantity'] ?? 1,
                'subprice' => $line['unit_price'] ?? null,
                'tva_tx' => $line['tax_rate'] ?? 0,
                'fk_product' => $this->toInteger($line['product_id'] ?? null),
            ], static fn (mixed $value): bool => $value !== null && $value !== '');

            $lineResponse = $this->authorizedClient($configuration, $token)
                ->post(sprintf('/api/index.php/invoices/%d/lines', $invoiceId), $linePayload);

            if (! $lineResponse->successful()) {
                throw new RuntimeException($this->responseDescription($lineResponse->status(), $lineResponse->body()));
            }

            $lineItems[] = $this->extractIdentifier($lineResponse);
        }

        return [
            'invoice_id' => $invoiceId,
            'customer_id' => $customerId,
            'invoice_date' => $invoiceDate,
            'line_count' => count($lineItems),
            'line_ids' => array_values(array_filter($lineItems)),
            'status' => 'draft',
        ];
    }

    /**
     * Return the endpoints most relevant for the first invoice automation flow.
     *
     * @param  array<int, string>  $discoveredApis
     * @return array<int, string>
     */
    public function importantApis(array $discoveredApis): array
    {
        $priorityOrder = [
            'login',
            'thirdparties',
            'products',
            'invoices',
            'proposals',
            'orders',
            'users',
        ];

        $importantApis = [];

        foreach ($priorityOrder as $apiName) {
            if (in_array($apiName, $discoveredApis, true)) {
                $importantApis[] = $apiName;
            }
        }

        return $importantApis;
    }

    /**
     * Translate a connection exception into a friendly message.
     */
    public function describeFailure(Throwable $exception): string
    {
        return 'No se pudo conectar con Dolibarr. Revisa la URL base y que la API REST este activa.';
    }

    private function httpClient(string $baseUrl): PendingRequest
    {
        return Http::baseUrl($baseUrl)
            ->acceptJson()
            ->timeout(15)
            ->connectTimeout(5);
    }

    private function authorizedClient(DolibarrConfiguration $configuration, string $token): PendingRequest
    {
        return $this->httpClient($this->apiBaseUrl($configuration))
            ->withHeaders([
                'DOLAPIKEY' => $token,
            ]);
    }

    private function baseUrl(): string
    {
        return rtrim((string) config('services.dolibarr.base_url', 'http://localhost'), '/');
    }

    private function apiBaseUrl(DolibarrConfiguration $configuration): string
    {
        return $this->normalizeBaseUrl($configuration->api_url ?: $this->baseUrl());
    }

    private function setupUrl(): string
    {
        return 'https://wiki.dolibarr.org/index.php/Module_Web_Services_API_REST_(developer)';
    }

    private function normalizeBaseUrl(string $url): string
    {
        $normalizedUrl = rtrim($url, '/');

        foreach ([
            '/api/index.php/explorer',
            '/api/index.php',
        ] as $suffix) {
            if (str_ends_with($normalizedUrl, $suffix)) {
                return rtrim(substr($normalizedUrl, 0, -strlen($suffix)), '/');
            }
        }

        return $normalizedUrl;
    }

    /**
     * @return array<int, string>
     */
    private function discoverApis(string $html): array
    {
        if (trim($html) === '') {
            return [];
        }

        $document = new DOMDocument();

        libxml_use_internal_errors(true);
        $document->loadHTML($html);
        libxml_clear_errors();

        $xpath = new DOMXPath($document);
        $nodes = $xpath->query('//body//*[self::h1 or self::h2 or self::h3 or self::h4 or self::summary or self::a]');

        if ($nodes === false) {
            return [];
        }

        $discoveredApis = [];

        foreach ($nodes as $node) {
            $label = $this->normalizeDiscoveryLabel($node->textContent ?? '');

            if ($label === null) {
                continue;
            }

            $discoveredApis[$label] = true;

            if (count($discoveredApis) >= 20) {
                break;
            }
        }

        return array_keys($discoveredApis);
    }

    private function normalizeDiscoveryLabel(string $label): ?string
    {
        $normalizedLabel = trim(preg_replace('/\s+/u', ' ', $label) ?? '');

        if ($normalizedLabel === '') {
            return null;
        }

        $normalizedLower = mb_strtolower($normalizedLabel);

        if (in_array($normalizedLower, [
            'show/hide',
            'list operations',
            'expand operations',
            'copy as markdown',
            'copy',
            'explore',
        ], true)) {
            return null;
        }

        return mb_strlen($normalizedLabel) >= 3 ? $normalizedLabel : null;
    }

    private function extractToken(Response $response): ?string
    {
        $json = $response->json();

        if (is_string($json) && trim($json) !== '') {
            return trim($json);
        }

        if (is_array($json)) {
            $token = data_get($json, 'success.token')
                ?? data_get($json, 'token')
                ?? data_get($json, 'api_token')
                ?? data_get($json, 'data.token');

            if (is_string($token) && trim($token) !== '') {
                return trim($token);
            }
        }

        $body = trim($response->body());

        return $body !== '' ? trim($body, "\" \n\r\t") : null;
    }

    /**
     * @param  array<string, mixed>|null  $payload
     * @return array<int, array<string, mixed>>
     */
    private function normalizeListPayload(mixed $payload): array
    {
        if (! is_array($payload)) {
            return [];
        }

        foreach (['result', 'data'] as $key) {
            if (isset($payload[$key]) && is_array($payload[$key])) {
                $payload = $payload[$key];
                break;
            }
        }

        if (array_is_list($payload)) {
            return array_values(array_filter($payload, 'is_array'));
        }

        return [];
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizeInvoicePayload(mixed $payload): array
    {
        if (! is_array($payload)) {
            return [];
        }

        foreach (['result', 'data'] as $key) {
            if (isset($payload[$key]) && is_array($payload[$key])) {
                $payload = $payload[$key];
                break;
            }
        }

        if (array_is_list($payload)) {
            $first = array_values(array_filter($payload, 'is_array'))[0] ?? [];

            return is_array($first) ? $first : [];
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function normalizeInvoiceRecord(array $payload): array
    {
        return [
            'id' => $this->toInteger(data_get($payload, 'id') ?? data_get($payload, 'rowid')),
            'ref' => (string) (data_get($payload, 'ref') ?? ''),
            'reference' => (string) (data_get($payload, 'ref_client') ?? ''),
            'customerId' => $this->toInteger(data_get($payload, 'socid') ?? data_get($payload, 'fk_soc')),
            'customerName' => (string) (data_get($payload, 'socname') ?? data_get($payload, 'customer_name') ?? data_get($payload, 'thirdparty_name') ?? ''),
            'date' => data_get($payload, 'date') ?? data_get($payload, 'datef') ?? data_get($payload, 'date_facture'),
            'dateCreation' => data_get($payload, 'date_creation') ?? data_get($payload, 'datec'),
            'dateValidation' => data_get($payload, 'date_validation') ?? data_get($payload, 'datev'),
            'totalTtc' => data_get($payload, 'total_ttc') ?? data_get($payload, 'totalTTC') ?? data_get($payload, 'total'),
            'totalHt' => data_get($payload, 'total_ht') ?? data_get($payload, 'totalHT'),
            'status' => $this->toInteger(data_get($payload, 'status') ?? data_get($payload, 'statut')),
            'statusLabel' => data_get($payload, 'status_label') ?? data_get($payload, 'label_status'),
            'paid' => $this->toBoolean(data_get($payload, 'paid') ?? data_get($payload, 'paye')),
            'closeCode' => data_get($payload, 'close_code'),
            'closeNote' => data_get($payload, 'close_note'),
            'notePrivate' => data_get($payload, 'note_private'),
            'notePublic' => data_get($payload, 'note_public'),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function normalizeInvoiceLines(array $payload): array
    {
        $lines = data_get($payload, 'lines');

        if (! is_array($lines)) {
            return [];
        }

        $normalizedLines = [];

        foreach ($lines as $line) {
            if (! is_array($line)) {
                continue;
            }

            $normalizedLines[] = [
                'id' => $this->toInteger(data_get($line, 'id') ?? data_get($line, 'rowid')),
                'description' => (string) (data_get($line, 'desc') ?? data_get($line, 'description') ?? data_get($line, 'label') ?? ''),
                'quantity' => data_get($line, 'qty') ?? data_get($line, 'quantity'),
                'unitPrice' => data_get($line, 'subprice') ?? data_get($line, 'unit_price'),
                'totalTtc' => data_get($line, 'total_ttc') ?? data_get($line, 'totalTTC') ?? data_get($line, 'total'),
                'totalHt' => data_get($line, 'total_ht') ?? data_get($line, 'totalHT'),
                'taxRate' => data_get($line, 'tva_tx') ?? data_get($line, 'tax_rate'),
                'productId' => $this->toInteger(data_get($line, 'fk_product') ?? data_get($line, 'product_id')),
            ];
        }

        return $normalizedLines;
    }

    private function invoiceMatchesSearch(array $invoice, string $search): bool
    {
        if ($search === '') {
            return true;
        }

        return $this->matchesSearch($search, $invoice, [
            'ref',
            'reference',
            'customerName',
            'statusLabel',
            'closeCode',
            'closeNote',
        ]);
    }

    private function normalizeInvoiceStatusFilter(string $status): ?int
    {
        $normalized = mb_strtolower(trim($status));

        if ($normalized === '') {
            return null;
        }

        return match ($normalized) {
            '0', 'draft', 'borrador' => 0,
            '1', 'validated', 'validada', 'validado', 'unpaid', 'open', 'abierta' => 1,
            '2', 'paid', 'pagada', 'pagado' => 2,
            '3', 'cancelled', 'canceled', 'anulada', 'anulado' => 3,
            default => null,
        };
    }

    private function normalizeInvoiceStatusCode(mixed $status): ?int
    {
        $normalized = mb_strtolower(trim((string) $status));

        if ($normalized === '') {
            return null;
        }

        return match ($normalized) {
            '0', 'draft', 'borrador' => 0,
            '1', 'validated', 'validada', 'validado' => 1,
            '2', 'paid', 'pagada', 'pagado' => 2,
            '3', 'cancelled', 'canceled', 'anulada', 'anulado' => 3,
            default => is_numeric($normalized) ? (int) $normalized : null,
        };
    }

    private function invoiceStatusLabel(int $status): string
    {
        return match ($status) {
            0 => 'Draft',
            1 => 'Validated',
            2 => 'Paid',
            3 => 'Cancelled',
            default => 'Unknown',
        };
    }

    private function normalizeDateFilter(string $value): ?string
    {
        $trimmed = trim($value);

        if ($trimmed === '') {
            return null;
        }

        try {
            return Carbon::parse($trimmed)->format('Ymd');
        } catch (Throwable) {
            return null;
        }
    }

    private function buildInvoiceSqlFilters(?string $dateFrom, ?string $dateTo): string
    {
        $clauses = [];

        if ($dateFrom !== null) {
            $clauses[] = sprintf("(t.datef:>=:'%s')", $dateFrom);
        }

        if ($dateTo !== null) {
            $clauses[] = sprintf("(t.datef:<=:'%s')", $dateTo);
        }

        return implode(' and ', $clauses);
    }

    /**
     * @param  array<string, mixed>  $item
     * @param  array<int, string>  $fields
     */
    private function matchesSearch(string $search, array $item, array $fields): bool
    {
        $normalizedSearch = mb_strtolower(trim($search));

        if ($normalizedSearch === '') {
            return true;
        }

        foreach ($fields as $field) {
            $value = data_get($item, $field);

            if (is_scalar($value) && str_contains(mb_strtolower((string) $value), $normalizedSearch)) {
                return true;
            }
        }

        return false;
    }

    private function toInteger(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_string($value) && is_numeric($value)) {
            return (int) $value;
        }

        if (is_float($value)) {
            return (int) $value;
        }

        return null;
    }

    private function toBoolean(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value) || is_float($value)) {
            return (bool) $value;
        }

        if (is_string($value)) {
            return in_array(mb_strtolower(trim($value)), ['1', 'true', 'yes', 'on'], true);
        }

        return false;
    }

    private function extractIdentifier(Response $response): ?int
    {
        $json = $response->json();

        $candidate = is_array($json)
            ? data_get($json, 'id')
                ?? data_get($json, 'result.id')
                ?? data_get($json, 'data.id')
            : null;

        if (is_int($candidate)) {
            return $candidate;
        }

        if (is_string($candidate) && is_numeric($candidate)) {
            return (int) $candidate;
        }

        $body = trim($response->body());

        return is_numeric($body) ? (int) $body : null;
    }

    /**
     * @return array{valid: bool, description: string, setup_url: string, discovered_apis: array<int, string>, important_apis: array<int, string>, discovered_api_count: int, important_api_count: int}
     */
    private function failureResult(string $description): array
    {
        return [
            'valid' => false,
            'description' => $description,
            'setup_url' => $this->setupUrl(),
            'discovered_apis' => [],
            'important_apis' => [],
            'discovered_api_count' => 0,
            'important_api_count' => 0,
        ];
    }

    private function loginFailureDescription(int $status, string $body): string
    {
        if (in_array($status, [401, 403], true)) {
            return 'El login o la contrasena de Dolibarr no son validos.';
        }

        return $body !== '' ? $body : 'Dolibarr no pudo autenticar las credenciales.';
    }

    private function responseDescription(int $status, string $body): string
    {
        if (in_array($status, [401, 403], true)) {
            return 'Dolibarr no autorizo el acceso con el token obtenido.';
        }

        return $body !== '' ? $body : 'Dolibarr no respondio correctamente.';
    }
}
