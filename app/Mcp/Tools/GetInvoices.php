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

#[Name('get_invoices')]
#[Description('List recent customer invoices available in the connected Dolibarr instance.')]
#[IsReadOnly]
class GetInvoices extends Tool
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
            $search = trim((string) $request->get('search', ''));
            $limit = max(1, min(100, (int) $request->get('limit', 10)));
            $result = $this->dolibarrApi->invoices($configuration, $search, $limit);
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
            'search' => $schema->string()->description('Optional text to filter invoices by reference, customer or status.')->nullable(),
            'limit' => $schema->integer()->description('Maximum number of invoices to return.')->default(10),
        ];
    }
}
