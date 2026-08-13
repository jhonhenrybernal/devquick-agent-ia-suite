<?php

namespace App\Mcp\Tools;

use App\Services\Dolibarr\DolibarrApi;
use App\Services\Dolibarr\DolibarrTeamResolver;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;
use Laravel\Mcp\Server\Tool;
use RuntimeException;

#[Name('search_invoices')]
#[Description('Search invoices available in the connected Dolibarr instance by text, status or date.')]
#[IsReadOnly]
class SearchInvoices extends Tool
{
    public function __construct(
        private DolibarrApi $dolibarrApi,
        private DolibarrTeamResolver $teamResolver,
    ) {
        //
    }

    /**
     * Handle the tool request.
     */
    public function handle(Request $request): Response|ResponseFactory
    {
        try {
            $configuration = $this->teamResolver->resolve($request);
            $result = $this->dolibarrApi->searchInvoices($configuration, $request->toArray());
        } catch (RuntimeException $exception) {
            return Response::error($exception->getMessage());
        }

        return Response::structured($result);
    }

    /**
     * Get the tool's input schema.
     *
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'team_slug' => $schema->string()->description('Optional team slug. Defaults to the current team.')->nullable(),
            'search' => $schema->string()->description('Text to search in invoice reference, customer name or notes.')->nullable(),
            'status' => $schema->string()->description('Optional invoice status such as draft, validated, paid or cancelled.')->nullable(),
            'thirdparty_ids' => $schema->string()->description('Optional Dolibarr third party IDs separated by commas.')->nullable(),
            'date_from' => $schema->string()->description('Optional lower bound date in YYYY-MM-DD format.')->nullable(),
            'date_to' => $schema->string()->description('Optional upper bound date in YYYY-MM-DD format.')->nullable(),
            'limit' => $schema->integer()->description('Maximum number of invoices to return.')->default(20),
            'page_size' => $schema->integer()->description('Maximum number of invoices requested per API page.')->default(100),
        ];
    }
}
