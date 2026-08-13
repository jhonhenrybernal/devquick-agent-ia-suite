<?php

use App\Enums\TeamRole;
use App\Mcp\Servers\Dolibarr as DolibarrServer;
use App\Mcp\Tools\CreateInvoice;
use App\Mcp\Tools\GetInvoiceById;
use App\Mcp\Tools\GetCustomers;
use App\Mcp\Tools\GetInvoices;
use App\Mcp\Tools\SearchInvoices;
use App\Mcp\Tools\SearchProducts;
use App\Mcp\Tools\UpdateInvoice;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;

test('dolibarr mcp server exposes the billing tools', function () {
    DolibarrServer::tool(GetCustomers::class)
        ->assertName('get_customers')
        ->assertDescription('List customers available in the connected Dolibarr instance.');

    DolibarrServer::tool(GetInvoices::class)
        ->assertName('get_invoices')
        ->assertDescription('List recent customer invoices available in the connected Dolibarr instance.');

    DolibarrServer::tool(GetInvoiceById::class)
        ->assertName('get_invoice_by_id')
        ->assertDescription('Get the full details of a single invoice from the connected Dolibarr instance.');

    DolibarrServer::tool(SearchInvoices::class)
        ->assertName('search_invoices')
        ->assertDescription('Search invoices available in the connected Dolibarr instance by text, status or date.');

    DolibarrServer::tool(SearchProducts::class)
        ->assertName('search_products')
        ->assertDescription('Search products and services available in the connected Dolibarr instance.');

    DolibarrServer::tool(CreateInvoice::class)
        ->assertName('create_invoice')
        ->assertDescription('Create a draft customer invoice in the connected Dolibarr instance.');

    DolibarrServer::tool(UpdateInvoice::class)
        ->assertName('update_invoice')
        ->assertDescription('Update the state of an invoice in the connected Dolibarr instance.');
});

test('get customers returns the customers that match the search term', function () {
    Http::preventStrayRequests();

    Http::fake([
        'https://dolibarr.example.com/api/index.php/login*' => Http::response([
            'success' => [
                'token' => 'dolibarr-token',
            ],
        ], 200),
        'https://dolibarr.example.com/api/index.php/thirdparties*' => Http::response([
            [
                'id' => 10,
                'name' => 'Acme Services',
                'ref' => 'ACME-001',
                'city' => 'Bogota',
                'country' => 'CO',
                'customer' => 1,
            ],
            [
                'id' => 11,
                'name' => 'Other Company',
                'ref' => 'OTR-002',
                'city' => 'Medellin',
                'country' => 'CO',
                'customer' => 1,
            ],
        ], 200),
    ]);

    $owner = createDolibarrTeamOwner();

    $response = DolibarrServer::actingAs($owner)->tool(GetCustomers::class, [
        'search' => 'Acme',
        'limit' => 10,
    ]);

    $response
        ->assertOk()
        ->assertStructuredContent([
            'count' => 1,
            'customers' => [
                [
                    'id' => 10,
                    'name' => 'Acme Services',
                    'reference' => 'ACME-001',
                    'city' => 'Bogota',
                    'country' => 'CO',
                    'isCustomer' => true,
                    'isSupplier' => false,
                ],
            ],
        ]);

    Http::assertSent(fn (HttpRequest $request): bool => str_contains($request->url(), '/api/index.php/login')
        && str_contains($request->url(), 'login=dolibarr-user')
        && str_contains($request->url(), 'password=dolibarr-password'));

    Http::assertSent(fn (HttpRequest $request): bool => str_contains($request->url(), '/api/index.php/thirdparties')
        && $request->hasHeader('DOLAPIKEY', 'dolibarr-token'));
});

test('search products returns matching products', function () {
    Http::preventStrayRequests();

    Http::fake([
        'https://dolibarr.example.com/api/index.php/login*' => Http::response([
            'token' => 'dolibarr-token',
        ], 200),
        'https://dolibarr.example.com/api/index.php/products*' => Http::response([
            [
                'id' => 25,
                'ref' => 'SERV-001',
                'label' => 'Monthly Service',
                'description' => 'Monthly support service',
                'price' => 120000,
                'price_ttc' => 142800,
                'type' => 1,
            ],
            [
                'id' => 26,
                'ref' => 'PROD-002',
                'label' => 'Other Product',
                'description' => 'Other stock item',
                'price' => 50000,
                'price_ttc' => 59500,
                'type' => 0,
            ],
        ], 200),
    ]);

    $owner = createDolibarrTeamOwner();

    $response = DolibarrServer::actingAs($owner)->tool(SearchProducts::class, [
        'search' => 'support',
        'limit' => 10,
    ]);

    $response
        ->assertOk()
        ->assertStructuredContent([
            'count' => 1,
            'products' => [
                [
                    'id' => 25,
                    'ref' => 'SERV-001',
                    'label' => 'Monthly Service',
                    'description' => 'Monthly support service',
                    'price' => 120000,
                    'priceTtc' => 142800,
                    'type' => 1,
                ],
            ],
        ]);

    Http::assertSent(fn (HttpRequest $request): bool => str_contains($request->url(), '/api/index.php/products')
        && $request->hasHeader('DOLAPIKEY', 'dolibarr-token'));
});

test('get invoices returns recent invoices', function () {
    Http::preventStrayRequests();

    Http::fake([
        'https://dolibarr.example.com/api/index.php/login*' => Http::response([
            'success' => [
                'token' => 'dolibarr-token',
            ],
        ], 200),
        'https://dolibarr.example.com/api/index.php/invoices*' => Http::response([
            [
                'id' => 51,
                'ref' => 'FA-2026-0002',
                'ref_client' => 'CLIENTE-2026-09',
                'socname' => 'Other Company',
                'date' => '2026-08-10',
                'total_ttc' => 95000,
                'status' => 0,
                'label_status' => 'Draft',
            ],
            [
                'id' => 50,
                'ref' => 'FA-2026-0001',
                'ref_client' => 'CLIENTE-2026-08',
                'socname' => 'Acme Services',
                'date' => '2026-08-11',
                'total_ttc' => 180000,
                'status' => 1,
                'label_status' => 'Open',
            ],
        ], 200),
    ]);

    $owner = createDolibarrTeamOwner();

    $response = DolibarrServer::actingAs($owner)->tool(GetInvoices::class, [
        'limit' => 10,
    ]);

    $response
        ->assertOk()
        ->assertStructuredContent([
            'count' => 2,
            'invoices' => [
                [
                    'id' => 50,
                    'ref' => 'FA-2026-0001',
                    'reference' => 'CLIENTE-2026-08',
                    'customerName' => 'Acme Services',
                    'date' => '2026-08-11',
                    'totalTtc' => 180000,
                    'totalHt' => null,
                    'status' => 1,
                    'statusLabel' => 'Open',
                    'paid' => false,
                ],
                [
                    'id' => 51,
                    'ref' => 'FA-2026-0002',
                    'reference' => 'CLIENTE-2026-09',
                    'customerName' => 'Other Company',
                    'date' => '2026-08-10',
                    'totalTtc' => 95000,
                    'totalHt' => null,
                    'status' => 0,
                    'statusLabel' => 'Draft',
                    'paid' => false,
                ],
            ],
        ]);

    Http::assertSent(fn (HttpRequest $request): bool => str_contains($request->url(), '/api/index.php/invoices')
        && ! str_contains($request->url(), 'sortfield=')
        && $request->hasHeader('DOLAPIKEY', 'dolibarr-token'));
});

test('create invoice uses the configured customer and invoice lines', function () {
    Http::preventStrayRequests();

    Http::fake([
        'https://dolibarr.example.com/api/index.php/login*' => Http::response([
            'success' => [
                'token' => 'dolibarr-token',
            ],
        ], 200),
        'https://dolibarr.example.com/api/index.php/invoices' => Http::response([
            'id' => 500,
            'ref' => 'FA-2026-0001',
        ], 200),
        'https://dolibarr.example.com/api/index.php/invoices/500/lines' => Http::response([
            'id' => 700,
        ], 200),
    ]);

    $owner = createDolibarrTeamOwner();

    $response = DolibarrServer::actingAs($owner)->tool(CreateInvoice::class, [
        'customer_id' => 10,
        'reference' => 'MENSUAL-2026-08',
        'invoice_date' => '2026-08-03',
        'note_private' => 'Factura mensual generada por agente',
        'note_public' => 'Servicio de soporte mensual',
        'lines' => [
            [
                'description' => 'Servicio mensual de soporte',
                'quantity' => 1,
                'unit_price' => 120000,
                'tax_rate' => 19,
                'product_id' => 25,
            ],
        ],
    ]);

    $response
        ->assertOk()
        ->assertStructuredContent([
            'invoice_id' => 500,
            'customer_id' => 10,
            'invoice_date' => '2026-08-03',
            'line_count' => 1,
            'line_ids' => [
                700,
            ],
            'status' => 'draft',
        ]);

    Http::assertSent(fn (HttpRequest $request): bool => str_ends_with($request->url(), '/api/index.php/invoices')
        && $request->method() === 'POST'
        && data_get($request->data(), 'socid') === 10
        && data_get($request->data(), 'ref_client') === 'MENSUAL-2026-08');

    Http::assertSent(fn (HttpRequest $request): bool => str_contains($request->url(), '/api/index.php/invoices/500/lines')
        && $request->method() === 'POST'
        && data_get($request->data(), 'desc') === 'Servicio mensual de soporte'
        && data_get($request->data(), 'fk_product') === 25);
});

test('get invoice by id returns the invoice details and lines', function () {
    Http::preventStrayRequests();

    Http::fake([
        'https://dolibarr.example.com/api/index.php/login*' => Http::response([
            'success' => [
                'token' => 'dolibarr-token',
            ],
        ], 200),
        'https://dolibarr.example.com/api/index.php/invoices/501*' => Http::response([
            'id' => 501,
            'ref' => 'FA-2026-0007',
            'ref_client' => 'CLIENTE-2026-11',
            'socid' => 10,
            'socname' => 'Acme Services',
            'date' => '2026-08-11',
            'total_ttc' => 180000,
            'total_ht' => 151260,
            'status' => 1,
            'label_status' => 'Validated',
            'lines' => [
                [
                    'id' => 700,
                    'desc' => 'Servicio mensual de soporte',
                    'qty' => 1,
                    'subprice' => 151260,
                    'tva_tx' => 19,
                    'fk_product' => 25,
                ],
            ],
        ], 200),
    ]);

    $owner = createDolibarrTeamOwner();

    $response = DolibarrServer::actingAs($owner)->tool(GetInvoiceById::class, [
        'invoice_id' => 501,
    ]);

    $response
        ->assertOk()
        ->assertStructuredContent([
            'invoice' => [
                'id' => 501,
                'ref' => 'FA-2026-0007',
                'reference' => 'CLIENTE-2026-11',
                'customerId' => 10,
                'customerName' => 'Acme Services',
                'date' => '2026-08-11',
                'dateCreation' => null,
                'dateValidation' => null,
                'totalTtc' => 180000,
                'totalHt' => 151260,
                'status' => 1,
                'statusLabel' => 'Validated',
                'paid' => false,
                'closeCode' => null,
                'closeNote' => null,
                'notePrivate' => null,
                'notePublic' => null,
            ],
            'lines' => [
                [
                    'id' => 700,
                    'description' => 'Servicio mensual de soporte',
                    'quantity' => 1,
                    'unitPrice' => 151260,
                    'totalTtc' => null,
                    'totalHt' => null,
                    'taxRate' => 19,
                    'productId' => 25,
                ],
            ],
            'line_count' => 1,
        ]);

    Http::assertSent(fn (HttpRequest $request): bool => str_contains($request->url(), '/api/index.php/invoices/501')
        && $request->hasHeader('DOLAPIKEY', 'dolibarr-token'));
});

test('search invoices filters by status, dates and text', function () {
    Http::preventStrayRequests();

    Http::fake([
        'https://dolibarr.example.com/api/index.php/login*' => Http::response([
            'success' => [
                'token' => 'dolibarr-token',
            ],
        ], 200),
        'https://dolibarr.example.com/api/index.php/invoices*' => Http::response([
            [
                'id' => 602,
                'ref' => 'FA-2026-0010',
                'ref_client' => 'CLIENTE-2026-10',
                'socname' => 'Acme Services',
                'date' => '2026-08-11',
                'total_ttc' => 180000,
                'status' => 2,
                'label_status' => 'Paid',
            ],
            [
                'id' => 603,
                'ref' => 'FA-2026-0011',
                'ref_client' => 'CLIENTE-2026-11',
                'socname' => 'Other Company',
                'date' => '2026-07-15',
                'total_ttc' => 95000,
                'status' => 1,
                'label_status' => 'Validated',
            ],
        ], 200),
    ]);

    $owner = createDolibarrTeamOwner();

    $response = DolibarrServer::actingAs($owner)->tool(SearchInvoices::class, [
        'search' => 'Acme',
        'status' => 'paid',
        'date_from' => '2026-08-01',
        'date_to' => '2026-08-31',
        'limit' => 10,
        'page_size' => 50,
    ]);

    $response
        ->assertOk()
        ->assertStructuredContent([
            'count' => 1,
            'invoices' => [
                [
                    'id' => 602,
                    'ref' => 'FA-2026-0010',
                    'reference' => 'CLIENTE-2026-10',
                    'customerId' => null,
                    'customerName' => 'Acme Services',
                    'date' => '2026-08-11',
                    'dateCreation' => null,
                    'dateValidation' => null,
                    'totalTtc' => 180000,
                    'totalHt' => null,
                    'status' => 2,
                    'statusLabel' => 'Paid',
                    'paid' => false,
                    'closeCode' => null,
                    'closeNote' => null,
                    'notePrivate' => null,
                    'notePublic' => null,
                ],
            ],
            'filters' => [
                'search' => 'Acme',
                'status' => 2,
                'thirdparty_ids' => null,
                'date_from' => '20260801',
                'date_to' => '20260831',
            ],
        ]);

    Http::assertSent(function (HttpRequest $request): bool {
        $url = rawurldecode($request->url());

        return str_contains($url, '/api/index.php/invoices')
            && str_contains($url, 'sortfield=t.rowid')
            && str_contains($url, 'sortorder=DESC')
            && str_contains($url, 'status=2')
            && str_contains($url, "sqlfilters=(t.datef:>=:'20260801') and (t.datef:<=:'20260831')")
            && $request->hasHeader('DOLAPIKEY', 'dolibarr-token');
    });
});

test('update invoice changes the invoice status and returns the updated state', function () {
    Http::preventStrayRequests();

    Http::fake([
        'https://dolibarr.example.com/api/index.php/login*' => Http::response([
            'success' => [
                'token' => 'dolibarr-token',
            ],
        ], 200),
        'https://dolibarr.example.com/api/index.php/invoices/501*' => Http::response([
            'id' => 501,
            'ref' => 'FA-2026-0007',
            'ref_client' => 'CLIENTE-2026-11',
            'socid' => 10,
            'socname' => 'Acme Services',
            'date' => '2026-08-11',
            'total_ttc' => 180000,
            'total_ht' => 151260,
            'status' => 2,
            'label_status' => 'Paid',
            'lines' => [
                [
                    'id' => 700,
                    'desc' => 'Servicio mensual de soporte',
                    'qty' => 1,
                    'subprice' => 151260,
                    'tva_tx' => 19,
                    'fk_product' => 25,
                ],
            ],
        ], 200),
    ]);

    $owner = createDolibarrTeamOwner();

    $response = DolibarrServer::actingAs($owner)->tool(UpdateInvoice::class, [
        'invoice_id' => 501,
        'status' => 'paid',
        'close_code' => 'paid',
        'close_note' => 'Factura marcada como pagada',
    ]);

    $response
        ->assertOk()
        ->assertStructuredContent([
            'updated' => true,
            'invoice_id' => 501,
            'status' => 2,
            'status_label' => 'Paid',
            'close_code' => 'paid',
            'close_note' => 'Factura marcada como pagada',
            'invoice' => [
                'id' => 501,
                'ref' => 'FA-2026-0007',
                'reference' => 'CLIENTE-2026-11',
                'customerId' => 10,
                'customerName' => 'Acme Services',
                'date' => '2026-08-11',
                'dateCreation' => null,
                'dateValidation' => null,
                'totalTtc' => 180000,
                'totalHt' => 151260,
                'status' => 2,
                'statusLabel' => 'Paid',
                'paid' => false,
                'closeCode' => null,
                'closeNote' => null,
                'notePrivate' => null,
                'notePublic' => null,
            ],
            'lines' => [
                [
                    'id' => 700,
                    'description' => 'Servicio mensual de soporte',
                    'quantity' => 1,
                    'unitPrice' => 151260,
                    'totalTtc' => null,
                    'totalHt' => null,
                    'taxRate' => 19,
                    'productId' => 25,
                ],
            ],
            'line_count' => 1,
        ]);

    Http::assertSent(fn (HttpRequest $request): bool => str_contains($request->url(), '/api/index.php/invoices/501')
        && $request->method() === 'PUT'
        && $request->hasHeader('DOLAPIKEY', 'dolibarr-token'));
});

function createDolibarrTeamOwner(): User
{
    $owner = User::factory()->create();
    $team = Team::factory()->create();

    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
    $team->dolibarrConfiguration()->create([
        'api_login' => 'dolibarr-user',
        'api_password' => 'dolibarr-password',
        'api_url' => 'https://dolibarr.example.com/api/index.php/explorer/',
    ]);
    $owner->switchTeam($team);

    return $owner;
}
