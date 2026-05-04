<?php declare(strict_types=1);

use Composer\InstalledVersions;
use Shopware\Core\Framework\Feature;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

/** @codeCoverageIgnore */
return static function (ContainerConfigurator $container): void {
    $flagActive = Feature::isActive('MCP_SERVER');
    $bundleInstalled = InstalledVersions::isInstalled('symfony/mcp-bundle');

    if ($flagActive && !$bundleInstalled) {
        throw new RuntimeException(
            'MCP_SERVER feature flag is active but symfony/mcp-bundle is not installed. '
            . 'Run "composer install" to install all required dependencies, '
            . 'or disable the flag by removing MCP_SERVER=1 from your .env file.'
        );
    }

    if (!$flagActive || !$bundleInstalled) {
        return;
    }

    $container->extension('mcp', [
        'app' => 'Shopware',
        'version' => '1.0.0',
        'description' => 'Shopware MCP server providing tools for entity management, system configuration, and storefront operations.',
        'instructions' => "This MCP server exposes Shopware e-commerce platform capabilities.\nUse entity tools to search, read, and manage shop data.\nAll operations respect the authenticated user's ACL permissions.\n",
        'client_transports' => ['http' => true],
        'http' => ['path' => '/api/_mcp'],
        'discovery' => [
            'scan_dirs' => [
                'src/Core/Framework/Mcp',
                'src/Storefront/Mcp',
            ],
        ],
    ]);
};
