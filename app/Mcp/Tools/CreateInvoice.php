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

#[Name('create_invoice')]
#[Description('Create a draft customer invoice in the connected Dolibarr instance.')]
#[IsDestructive]
class CreateInvoice extends Tool
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
            $result = $this->dolibarrApi->createInvoice($configuration, $request->toArray());
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
            'customer_id' => $schema->integer()->description('Dolibarr third party ID for the customer.')->required(),
            'reference' => $schema->string()->description('Optional customer reference to store on the invoice.')->nullable(),
            'invoice_date' => $schema->string()->description('Optional invoice date in YYYY-MM-DD format.')->nullable(),
            'note_private' => $schema->string()->description('Optional private note for the invoice.')->nullable(),
            'note_public' => $schema->string()->description('Optional public note for the invoice.')->nullable(),
            'lines' => $schema->array()
                ->description('Invoice lines to create on the draft invoice.')
                ->min(1)
                ->items(
                    $schema->object([
                        'description' => $schema->string()->description('Line description.')->required(),
                        'quantity' => $schema->number()->description('Quantity to invoice.')->default(1),
                        'unit_price' => $schema->number()->description('Unit price before tax.')->required(),
                        'tax_rate' => $schema->number()->description('VAT percentage for the line.')->default(0),
                        'product_id' => $schema->integer()->description('Optional Dolibarr product ID for the line.')->nullable(),
                    ])->withoutAdditionalProperties()
                )
                ->required(),
        ];
    }
}
