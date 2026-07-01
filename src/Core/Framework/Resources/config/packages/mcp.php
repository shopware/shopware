<?php declare(strict_types=1);

use Shopware\Core\Framework\Framework;
use Shopware\Storefront\Storefront;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\Filesystem\Path;

/** @codeCoverageIgnore */
return static function (ContainerConfigurator $container, ContainerBuilder $builder): void {
    if (!$builder->hasExtension('mcp')) {
        return;
    }

    // The MCP SDK resolves scan_dirs relative to kernel.project_dir (the Discoverer
    // joins basePath . '/' . dir), so the directories must be derived from where the
    // bundles actually live. Hardcoding "src/Core/Framework/Mcp" only works for the
    // platform monorepo; in a Composer/production install the code sits under
    // vendor/shopware/* and discovery would find nothing (0 tools registered).
    $projectDir = (string) $builder->getParameter('kernel.project_dir');

    $scanDirs = [
        Path::makeRelative(\dirname((string) (new ReflectionClass(Framework::class))->getFileName()) . '/Mcp', $projectDir),
    ];
    if (class_exists(Storefront::class)) {
        $scanDirs[] = Path::makeRelative(\dirname((string) (new ReflectionClass(Storefront::class))->getFileName()) . '/Mcp', $projectDir);
    }

    $container->extension('mcp', [
        'app' => 'Shopware',
        'version' => '1.0.0',
        'description' => 'Shopware MCP server providing tools for entity management, system configuration, and storefront operations.',
        'instructions' => "This MCP server exposes Shopware e-commerce platform capabilities.\nUse entity tools to search, read, and manage shop data.\nAll operations respect the authenticated user's ACL permissions.\n",
        'client_transports' => ['http' => true],
        'http' => ['path' => '/api/_mcp'],
        'discovery' => [
            'scan_dirs' => $scanDirs,
        ],
    ]);
};
