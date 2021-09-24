<?php declare(strict_types=1);

namespace Shopware\Administration;

use Shopware\Core\Framework\Bundle;
use Shopware\Core\Framework\Migration\MigrationSource;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;

class Administration extends Bundle
{
    public function build(ContainerBuilder $container): void
    {
        parent::build($container);

        $loader = new XmlFileLoader($container, new FileLocator(__DIR__ . '/DependencyInjection/'));
        $loader->load('services.xml');

        // configure migration directories
        $migrationSourceV4 = $container->getDefinition(MigrationSource::class . '.core.V6_4');
        $migrationSourceV4->addMethodCall('addDirectory', [$this->getMigrationPath() . '/V6_4', $this->getMigrationNamespace() . '\V6_4']);

        // we've moved the migrations from Shopware\Core\Migration to Shopware\Core\Migration\v6_4
        $migrationSourceV4->addMethodCall('addReplacementPattern', ['#^(Shopware\\\\Administration\\\\Migration\\\\)V6_4\\\\([^\\\\]*)$#', '$1$2']);
    }
}
