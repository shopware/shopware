<?php declare(strict_types=1);

namespace Shopware\Storefront\DependencyInjection;

use Shopware\Core\Framework\Migration\MigrationSource;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class StorefrontMigrationReplacementCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        $migrationPath = \dirname(__DIR__) . '/Migration';

        $migrationSourceV3 = $container->getDefinition(MigrationSource::class . '.core.V6_3');
        $migrationSourceV3->addMethodCall('addDirectory', [$migrationPath . '/V6_3', 'Shopware\Storefront\Migration\V6_3']);

        // we've moved the migrations from Shopware\Core\Migration to Shopware\Core\Migration\v6_3
        $migrationSourceV3->addMethodCall('addReplacementPattern', ['#^(Shopware\\\\Storefront\\\\Migration\\\\)V6_3\\\\([^\\\\]*)$#', '$1$2']);

        $migrationSourceV4 = $container->getDefinition(MigrationSource::class . '.core.V6_4');
        $migrationSourceV4->addMethodCall('addDirectory', [$migrationPath . '/V6_4', 'Shopware\Storefront\Migration\V6_4']);
        $migrationSourceV3->addMethodCall('addReplacementPattern', ['#^(Shopware\\\\Storefront\\\\Migration\\\\)V6_4\\\\([^\\\\]*)$#', '$1$2']);

        $migrationSourceV5 = $container->getDefinition(MigrationSource::class . '.core.V6_5');
        $migrationSourceV5->addMethodCall('addDirectory', [$migrationPath . '/V6_5', 'Shopware\Storefront\Migration\V6_5']);
    }
}
