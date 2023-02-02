<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Migration;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class MigrationCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        $migrationCollections = $container->get(MigrationCollectionLoader::class)->collectAll();

        $activeCollectionMigrations = [[]];
        foreach ($migrationCollections as $collection) {
            $activeCollectionMigrations[] = $collection->getActiveMigrationTimestamps();
        }

        $container->setParameter('migration.active', array_merge(...$activeCollectionMigrations));
    }
}
