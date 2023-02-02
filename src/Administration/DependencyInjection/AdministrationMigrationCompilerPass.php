<?php declare(strict_types=1);

namespace Shopware\Administration\DependencyInjection;

use Shopware\Core\Framework\Migration\MigrationSource;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class AdministrationMigrationCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        $migrationPath = \dirname(__DIR__) . '/Migration';

        // configure migration directories
        $migrationSourceV4 = $container->getDefinition(MigrationSource::class . '.core.V6_4');
        $migrationSourceV4->addMethodCall('addDirectory', [$migrationPath . '/V6_4', 'Shopware\Administration\Migration\V6_4']);

        // we've moved the migrations from Shopware\Administration\Migration to Shopware\Administration\Migration\v6_4
        $migrationSourceV4->addMethodCall('addReplacementPattern', ['#^(Shopware\\\\Administration\\\\Migration\\\\)V6_4\\\\([^\\\\]*)$#', '$1$2']);
    }
}
