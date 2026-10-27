<?php declare(strict_types=1);

use Shopware\Core\Framework\Mcp\McpServerInstructions;
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
    // added when that bundle is actually installed. It can still be emptied out later -- the
    // namespace holds exactly one tool, so `shopware.mcp.allowed_tools` without `shopware-theme-config`
    // orphans the prefix. McpToolDiscoveryCompilerPass::pruneUnmatchedPatterns() drops it again in
    // that case, so hiding a tool never aborts the container build.
    $bundles = $builder->getParameter('kernel.bundles');
    \assert(\is_array($bundles));

    $adminRegistry = ['Shopware\\Core\\Framework\\Mcp\\'];
    if (isset($bundles['Storefront'])) {
        $adminRegistry[] = 'Shopware\\Storefront\\Mcp\\';
    }

    // Cache hints (SEP-2549) on the answers of the stateless 2026-07-28 revision; the handshake
    // revisions carry none. Every answer is `private`, because both servers answer per caller: the
    // allowlist of the integration or user and `?toolsets=` shape the lists. A shared cache must never
    // hand them to another caller. Nothing is fresh by default. `server/discover` and the lists stay fresh for a
    // minute, a fallback for clients that do not follow `subscriptions/listen`, so an allowlist change reaches
    // every client within that minute. The reference resources name their own TTL, see McpResourceCacheHint.
    $listTtlMs = 60_000;
    $cache = [
        'ttl_ms' => 0,
        'scope' => 'private',
        'methods' => [
            'server/discover' => ['ttl_ms' => $listTtlMs, 'scope' => 'private'],
            'tools/list' => ['ttl_ms' => $listTtlMs, 'scope' => 'private'],
            'prompts/list' => ['ttl_ms' => $listTtlMs, 'scope' => 'private'],
            'resources/list' => ['ttl_ms' => $listTtlMs, 'scope' => 'private'],
            'resources/templates/list' => ['ttl_ms' => $listTtlMs, 'scope' => 'private'],
        ],
    ];

    $container->extension('mcp', [
        'servers' => [
            'admin' => [
                'name' => 'Shopware',
                'version' => '1.0.0',
                'description' => 'Shopware MCP server providing tools for entity management, system configuration, and storefront operations.',
                'instructions' => McpServerInstructions::ADMIN,
                // Both endpoints are routed by Shopware's own controllers (api.mcp.endpoint and
                // store-api.mcp.endpoint), which apply authentication, rate limiting and the
                // capability allowlist. The bundle's controller and route loader stay switched off.
                'transports' => ['http' => false, 'stdio' => false],
                'registry' => $adminRegistry,
                'cache' => $cache,
            ],
            'store_api' => [
                'name' => 'Shopware Store API',
                'version' => '1.0.0',
                'description' => 'Shopware Store API MCP server for sales-channel and customer-context operations.',
                'instructions' => McpServerInstructions::STORE_API,
                'transports' => ['http' => false, 'stdio' => false],
                'registry' => [
                    'tools' => ['Shopware\\Core\\System\\SalesChannel\\Mcp\\'],
                    'resource_templates' => ['Shopware\\Core\\System\\SalesChannel\\Mcp\\Resource\\'],
                ],
                'cache' => $cache,
            ],
        ],
    ]);
};
