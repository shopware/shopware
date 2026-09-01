<?php declare(strict_types=1);

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

/** @codeCoverageIgnore */
return static function (ContainerConfigurator $container, ContainerBuilder $builder): void {
    if (!$builder->hasExtension('mcp')) {
        return;
    }

    // Shopware exposes two MCP servers, and the bundle assigns an element to a server by matching
    // the patterns below against its service id and class. Core and in-tree bundle capabilities live
    // in stable namespaces, so a prefix per server keeps the two endpoints disjoint.
    //
    // Plugin and third-party bundle capabilities cannot be expressed here: their namespace is
    // arbitrary, and the wildcard is not an option because it would match the other server's
    // elements too. McpToolDiscoveryCompilerPass appends those class names to the
    // "mcp.servers.elements" parameter instead, which is the channel the bundle's own compiler pass
    // reads its per-server element lists from.
    //
    // A pattern that matches nothing is a fatal error in the bundle, so the Storefront prefix is only
    // added when that bundle is actually installed.
    $bundles = $builder->getParameter('kernel.bundles');
    \assert(\is_array($bundles));

    $adminRegistry = ['Shopware\\Core\\Framework\\Mcp\\'];
    if (isset($bundles['Storefront'])) {
        $adminRegistry[] = 'Shopware\\Storefront\\Mcp\\';
    }

    $container->extension('mcp', [
        'servers' => [
            'admin' => [
                'name' => 'Shopware',
                'version' => '1.0.0',
                'description' => 'Shopware MCP server providing tools for entity management, system configuration, and storefront operations.',
                'instructions' => "This MCP server exposes Shopware e-commerce platform capabilities.\nUse entity tools to search, read, and manage shop data.\nThe advertised tool list is not the full catalogue. If no advertised tool matches the requested action, call shopware-tool-search first instead of assuming the action is unsupported; use shopware-toolsets-list and shopware-toolset-enable to make a matched tool callable if your client cannot invoke it inline.\nAll operations respect the authenticated user's ACL permissions.\n",
                // Both endpoints are routed by Shopware's own controllers (api.mcp.endpoint and
                // store-api.mcp.endpoint), which apply authentication, rate limiting and the
                // capability allowlist. The bundle's controller and route loader stay switched off.
                'transports' => ['http' => false, 'stdio' => false],
                'registry' => $adminRegistry,
            ],
            'store_api' => [
                'name' => 'Shopware Store API',
                'version' => '1.0.0',
                'description' => 'Shopware Store API MCP server for sales-channel and customer-context operations.',
                'instructions' => 'This MCP server exposes Store API capabilities. All operations run in the current sales-channel context and use Store API authentication headers. The advertised tool list is not the full catalogue: if no advertised tool matches the requested action, call shopware-tool-search first instead of assuming the action is unsupported, then use shopware-toolsets-list and shopware-toolset-enable to make a matched tool callable if your client cannot invoke it inline.',
                'transports' => ['http' => false, 'stdio' => false],
                'registry' => ['tools' => ['Shopware\\Core\\System\\SalesChannel\\Mcp\\']],
            ],
        ],
    ]);
};
