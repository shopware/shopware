<?php declare(strict_types=1);

namespace Shopware\Core\System;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Shopware\Core\Framework\Bundle;
use Shopware\Core\System\DependencyInjection\CompilerPass\RedisNumberRangeIncrementerCompilerPass;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEventFactory;
use Shopware\Core\Framework\DataAbstractionLayer\Read\EntityReaderInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntityAggregatorInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearcherInterface;
use Shopware\Core\Framework\DataAbstractionLayer\VersionManager;
use Shopware\Core\System\CustomEntity\Schema\DynamicEntityDefinition;
use Shopware\Core\System\DependencyInjection\CompilerPass\SalesChannelEntityCompilerPass;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;

class System extends Bundle
{
    /**
     * {@inheritdoc}
     */
    public function build(ContainerBuilder $container): void
    {
        parent::build($container);

        $loader = new XmlFileLoader($container, new FileLocator(__DIR__ . '/DependencyInjection/'));
        $loader->load('sales_channel.xml');
        $loader->load('country.xml');
        $loader->load('currency.xml');
        $loader->load('custom_entity.xml');
        $loader->load('locale.xml');
        $loader->load('snippet.xml');
        $loader->load('salutation.xml');
        $loader->load('tax.xml');
        $loader->load('unit.xml');
        $loader->load('user.xml');
        $loader->load('integration.xml');
        $loader->load('state_machine.xml');
        $loader->load('configuration.xml');
        $loader->load('number_range.xml');
        $loader->load('tag.xml');

        $container->addCompilerPass(new SalesChannelEntityCompilerPass());
        $container->addCompilerPass(new RedisNumberRangeIncrementerCompilerPass());
    }

    public function boot(): void
    {
        parent::boot();

        $this->registerCustomEntities(
            $this->container,
            $this->container->get(Connection::class),
            $this->container->get(DefinitionInstanceRegistry::class),
        );
    }

    private function registerCustomEntities(ContainerInterface $container, Connection $connection, DefinitionInstanceRegistry $registry): void
    {
        if (!$connection->isConnected()) {
            return;
        }

        try {
            $entities = $connection->fetchAllAssociative('SELECT name, fields FROM custom_entity');
        } catch (Exception $e) {
            // kernel booted without database connection, or booted for migration and custom entity table not created yet
            return;
        }

        $definitions = [];
        foreach ($entities as $entity) {
            $fields = json_decode($entity['fields'], true, 512, \JSON_THROW_ON_ERROR);

            $definition = DynamicEntityDefinition::create($entity['name'], $fields, $container);

            $definitions[] = $definition;

            $this->container->set($definition->getEntityName(), $definition);
            $this->container->set($definition->getEntityName() . '.repository', $this->createRepository($definition));
            $registry->register($definition, $definition->getEntityName());
        }

        foreach ($definitions as $definition) {
            // triggers field generation to generate reverse foreign keys, translation definitions and mapping definitions
            $definition->getFields();
        }
    }

    private function createRepository(DynamicEntityDefinition $definition): EntityRepository
    {
        return new EntityRepository(
            $definition,
            $this->container->get(EntityReaderInterface::class),
            $this->container->get(VersionManager::class),
            $this->container->get(EntitySearcherInterface::class),
            $this->container->get(EntityAggregatorInterface::class),
            $this->container->get('event_dispatcher'),
            $this->container->get(EntityLoadedEventFactory::class)
        );
    }
}
