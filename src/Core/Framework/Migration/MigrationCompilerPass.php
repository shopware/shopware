<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Migration;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class MigrationCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container)
    {
        /** @var MigrationCollectionLoader $migrationCollector */
        $migrationCollector = $container->get(MigrationCollection::class);

        $container->setParameter('migration.active', $migrationCollector->getActiveMigrationTimestamps());
    }
}
