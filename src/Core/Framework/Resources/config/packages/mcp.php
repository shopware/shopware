<?php declare(strict_types=1);

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\Filesystem\Path;

/** @codeCoverageIgnore */
return static function (ContainerConfigurator $container, ContainerBuilder $builder): void {
    if (!$builder->hasExtension('mcp')) {
        return;
    }

    // The MCP SDK resolves scan_dirs relative to kernel.project_dir (the Discoverer
    // joins basePath . '/' . dir), so the directories must point at where the bundles
    // actually live. Hardcoding "src/Core/Framework/Mcp" only works for the platform
    // monorepo; in a Composer/production install the code sits under vendor/shopware/*
    // and discovery would find nothing (0 tools registered). Deriving the paths from
    // the bundle metadata the kernel already computed keeps discovery correct in every
    // layout without runtime cost (this closure only runs at container compile time).
    $projectDir = (string) $builder->getParameter('kernel.project_dir');
    $bundles = $builder->getParameter('kernel.bundles_metadata');

    $scanDirs = [Path::makeRelative($bundles['Framework']['path'] . '/Mcp', $projectDir)];
    if (isset($bundles['Storefront'])) {
        $scanDirs[] = Path::makeRelative($bundles['Storefront']['path'] . '/Mcp', $projectDir);
    }

    $container->extension('mcp', [
        'app' => 'Shopware',
        'version' => '1.0.0',
        'description' => 'Shopware MCP server providing tools for entity management, system configuration, and storefront operations.',
        'instructions' => "This MCP server exposes Shopware e-commerce platform capabilities.\nUse entity tools to search, read, and manage shop data.\nThe advertised tool list is not the full catalogue. If no advertised tool matches the requested action, call shopware-tool-search first instead of assuming the action is unsupported; use shopware-toolsets-list and shopware-toolset-enable to make a matched tool callable if your client cannot invoke it inline.\nAll operations respect the authenticated user's ACL permissions.\n",
        'client_transports' => ['http' => true],
        'http' => ['path' => '/api/_mcp'],
        'discovery' => [
            'scan_dirs' => $scanDirs,
        ],
    ]);
};
