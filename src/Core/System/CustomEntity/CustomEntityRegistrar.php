<?php declare(strict_types=1);

namespace Shopware\Core\System\CustomEntity;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEventFactory;
use Shopware\Core\Framework\DataAbstractionLayer\Read\EntityReaderInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntityAggregatorInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearcherInterface;
use Shopware\Core\Framework\DataAbstractionLayer\VersionManager;
use Shopware\Core\System\CustomEntity\Schema\DynamicEntityDefinition;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * @internal
 */
class CustomEntityRegistrar
{
    private ContainerInterface $container;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    public function register(): void
    {
        if (!$this->container->get(Connection::class)->isConnected()) {
            return;
        }

        try {
            $entities = $this->container->get(Connection::class)->fetchAllAssociative('
                SELECT custom_entity.name, custom_entity.fields 
                FROM custom_entity
                    LEFT JOIN app ON app.id = custom_entity.app_id
                WHERE custom_entity.app_id IS NULL OR app.active = 1
            ');
        } catch (Exception $e) {
            // kernel booted without database connection, or booted for migration and custom entity table not created yet
            return;
        }

        $definitions = [];
        $registry = $this->container->get(DefinitionInstanceRegistry::class);

        foreach ($entities as $entity) {
            $fields = json_decode($entity['fields'], true, 512, \JSON_THROW_ON_ERROR);

            $definition = DynamicEntityDefinition::create($entity['name'], $fields, $this->container);

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
