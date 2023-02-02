<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DependencyInjection\CompilerPass;

use Shopware\Core\Framework\Migration\MigrationSource;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class FrameworkMigrationReplacementCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        $bundleRoot = \dirname(__DIR__, 3);

        $migrationSourceV3 = $container->getDefinition(MigrationSource::class . '.core.V6_3');
        $migrationSourceV3->addMethodCall('addDirectory', [$bundleRoot . '/Migration/V6_3', 'Shopware\Core\Migration\V6_3']);

        // we've moved the migrations from Shopware\Core\Migration to Shopware\Core\Migration\V6_3
        $migrationSourceV3->addMethodCall('addReplacementPattern', ['#^(Shopware\\\\Core\\\\Migration\\\\)V6_3\\\\([^\\\\]*)$#', '$1$2']);

        $migrationSourceV4 = $container->getDefinition(MigrationSource::class . '.core.V6_4');
        $migrationSourceV4->addMethodCall('addDirectory', [$bundleRoot . '/Migration/V6_4', 'Shopware\Core\Migration\V6_4']);
        $migrationSourceV3->addMethodCall('addReplacementPattern', ['#^(Shopware\\\\Core\\\\Migration\\\\)V6_4\\\\([^\\\\]*)$#', '$1$2']);

        $migrationSourceV5 = $container->getDefinition(MigrationSource::class . '.core.V6_5');
        $migrationSourceV5->addMethodCall('addDirectory', [$bundleRoot . '/Migration/V6_5', 'Shopware\Core\Migration\V6_5']);
    }
}
