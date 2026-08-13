<?php

namespace App\Mcp\Servers;

use App\Mcp\Tools\CreateInvoice;
use App\Mcp\Tools\GetInvoiceById;
use App\Mcp\Tools\GetCustomers;
use App\Mcp\Tools\GetInvoices;
use App\Mcp\Tools\SearchInvoices;
use App\Mcp\Tools\SearchProducts;
use App\Mcp\Tools\UpdateInvoice;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;

#[Name('Dolibarr')]
#[Version('0.0.1')]
#[Instructions('Use the Dolibarr tools to work with the current team configuration. Search customers, products and invoices, inspect invoice details, update invoice state when needed, and create draft invoices from the connected Dolibarr instance.')]
class Dolibarr extends Server
{
    protected array $tools = [
        GetCustomers::class,
        GetInvoices::class,
        GetInvoiceById::class,
        SearchInvoices::class,
        SearchProducts::class,
        CreateInvoice::class,
        UpdateInvoice::class,
    ];

    protected array $resources = [
        //
    ];

    protected array $prompts = [
        //
    ];
}
