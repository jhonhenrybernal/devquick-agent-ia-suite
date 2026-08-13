<?php

use App\Mcp\Servers\Dolibarr as DolibarrMcpServer;
use Laravel\Mcp\Facades\Mcp;

$mcpRoute = Mcp::web('/mcp/dolibarr', DolibarrMcpServer::class);

$mcpRoute->middleware('mcp.shared-token');
