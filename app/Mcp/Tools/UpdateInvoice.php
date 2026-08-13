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
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;
use Laravel\Mcp\Server\Tool;
use RuntimeException;

#[Name('update_invoice')]
#[Description('Update the state of an invoice in the connected Dolibarr instance.')]
#[IsDestructive]
class UpdateInvoice extends Tool
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
            $result = $this->dolibarrApi->updateInvoice($configuration, (int) $request->get('invoice_id'), $request->toArray());
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
            'invoice_id' => $schema->integer()->description('Dolibarr invoice ID.')->required(),
            'status' => $schema->string()->description('Invoice status: draft, validated, paid or cancelled.')->required(),
            'close_code' => $schema->string()->description('Optional closing code when cancelling an invoice.')->nullable(),
            'close_note' => $schema->string()->description('Optional closing note when cancelling an invoice.')->nullable(),
        ];
    }
}
