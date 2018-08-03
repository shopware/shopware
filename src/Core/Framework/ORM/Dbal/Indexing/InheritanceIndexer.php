<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ORM\Dbal\Indexing;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\ProgressAdvancedEvent;
use Shopware\Core\Framework\Event\ProgressFinishedEvent;
use Shopware\Core\Framework\Event\ProgressStartedEvent;
use Shopware\Core\Framework\ORM\Dbal\Common\LastIdQuery;
use Shopware\Core\Framework\ORM\Dbal\EntityDefinitionQueryHelper;
use Shopware\Core\Framework\ORM\DefinitionRegistry;
use Shopware\Core\Framework\ORM\EntityDefinition;
use Shopware\Core\Framework\ORM\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\ORM\Event\EntityWrittenEvent;
use Shopware\Core\Framework\ORM\Field\AssociationInterface;
use Shopware\Core\Framework\ORM\Field\Field;
use Shopware\Core\Framework\ORM\Field\ManyToManyAssociationField;
use Shopware\Core\Framework\ORM\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\ORM\Field\OneToManyAssociationField;
use Shopware\Core\Framework\ORM\Field\TranslationsAssociationField;
use Shopware\Core\Framework\ORM\FieldCollection;
use Shopware\Core\Framework\ORM\Write\Flag\Inherited;
use Shopware\Core\Framework\Struct\Uuid;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class InheritanceIndexer implements IndexerInterface
{
    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var DefinitionRegistry
     */
    private $registry;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    public function __construct(Connection $connection, DefinitionRegistry $registry, EventDispatcherInterface $eventDispatcher)
    {
        $this->connection = $connection;
        $this->registry = $registry;
        $this->eventDispatcher = $eventDispatcher;
    }

    public function index(\DateTime $timestamp, string $tenantId): void
    {
        $context = Context::createDefaultContext($tenantId);

        foreach ($this->registry->getElements() as $definition) {
            /** @var string|EntityDefinition $definition */
            if (!$definition::isInheritanceAware()) {
                continue;
            }

            if (!$definition::getFields()->has('autoIncrement')) {
                continue;
            }

            $iterator = $this->createIterator($tenantId, $definition::getEntityName());

            $this->eventDispatcher->dispatch(
                ProgressStartedEvent::NAME,
                new ProgressStartedEvent('Start building inheritance for definition: ' . $definition::getEntityName(), $iterator->fetchCount())
            );

            while ($ids = $iterator->fetch()) {
                $ids = array_map(function ($id) {
                    return Uuid::fromBytesToHex($id);
                }, $ids);

                $this->update($definition, $ids, $context);

                $this->eventDispatcher->dispatch(
                    ProgressAdvancedEvent::NAME,
                    new ProgressAdvancedEvent(count($ids))
                );
            }

            $this->eventDispatcher->dispatch(
                ProgressFinishedEvent::NAME,
                new ProgressFinishedEvent('Finish building inheritance for definition: ' . $definition::getEntityName())
            );
        }
    }

    public function refresh(EntityWrittenContainerEvent $event): void
    {
        /** @var EntityWrittenEvent $nested */
        foreach ($event->getEvents() as $nested) {
            $definition = $nested->getDefinition();

            if ($definition::isInheritanceAware()) {
                $this->update($definition, $nested->getIds(), $nested->getContext());
            }
        }
    }

    private function update(string $definition, array $ids, Context $context): void
    {
        /** @var string|EntityDefinition $definition */
        $inherited = $definition::getFields()->filter(function (Field $field) {
            return $field->is(Inherited::class) && $field instanceof AssociationInterface;
        });

        $associations = $inherited->filter(function (Field $field) {
            return $field instanceof OneToManyAssociationField || $field instanceof ManyToManyAssociationField || $field instanceof TranslationsAssociationField;
        });

        if ($associations->count() > 0) {
            $this->updateToManyAssociations($definition, $ids, $associations, $context);
        }

        $associations = $inherited->filter(function (Field $field) {
            return $field instanceof ManyToOneAssociationField;
        });

        if ($associations->count() > 0) {
            $this->updateToOneAssociations($definition, $ids, $associations, $context);
        }
    }

    private function updateToManyAssociations(
        string $definition,
        array $ids,
        FieldCollection $associations,
        Context $context
    ): void {
        /* @var string|EntityDefinition $definition */
        $tenantId = Uuid::fromHexToBytes($context->getTenantId());
        $versionId = Uuid::fromHexToBytes($context->getVersionId());

        $bytes = array_map(function ($id) {
            return Uuid::fromHexToBytes($id);
        }, $ids);

        /** @var AssociationInterface $association */
        foreach ($associations as $association) {
            if ($association instanceof ManyToManyAssociationField) {
                $reference = $association->getMappingDefinition();
            } else {
                $reference = $association->getReferenceClass();
            }

            $parameters = [
                '#root#' => EntityDefinitionQueryHelper::escape($definition::getEntityName()),
                '#entity#' => $definition::getEntityName(),
                '#property#' => EntityDefinitionQueryHelper::escape($association->getPropertyName()),
                '#reference#' => EntityDefinitionQueryHelper::escape($reference::getEntityName()),
            ];

            $this->connection->executeUpdate(
                str_replace(
                    array_keys($parameters),
                    array_values($parameters),
                    'UPDATE #root# SET #property# = IFNULL(
                        (
                            SELECT #reference#.#entity#_id
                            FROM   #reference#
                            WHERE  #reference#.#entity#_id         = #root#.id
                            AND    #reference#.#entity#_version_id = #root#.version_id
                            AND    #reference#.#entity#_tenant_id  = #root#.tenant_id 
                            LIMIT 1
                        ),
                        #root#.parent_id
                     )
                     WHERE #root#.version_id = :version
                     AND #root#.tenant_id = :tenant
                     AND #root#.id IN (:ids)'
                ),
                ['version' => $versionId, 'tenant' => $tenantId, 'ids' => $bytes],
                ['version' => \PDO::PARAM_STR, 'tenant' => \PDO::PARAM_STR, 'ids' => Connection::PARAM_STR_ARRAY]
            );
        }
    }

    private function updateToOneAssociations(
        string $definition,
        array $ids,
        FieldCollection $associations,
        Context $context
    ): void {
        /** @var string|EntityDefinition $definition */
        $tenantId = Uuid::fromHexToBytes($context->getTenantId());
        $versionId = Uuid::fromHexToBytes($context->getVersionId());

        $bytes = array_map(function ($id) {
            return Uuid::fromHexToBytes($id);
        }, $ids);

        /** @var ManyToOneAssociationField $association */
        foreach ($associations as $association) {
            $parameters = [
                '#root#' => EntityDefinitionQueryHelper::escape($definition::getEntityName()),
                '#field#' => EntityDefinitionQueryHelper::escape($association->getStorageName()),
                '#property#' => EntityDefinitionQueryHelper::escape($association->getPropertyName()),
            ];

            $this->connection->executeUpdate(
                str_replace(
                    array_keys($parameters),
                    array_values($parameters),
                    'UPDATE #root# as #root#, #root# as parent
                     SET #root#.#property# = IFNULL(#root#.#field#, parent.#field#)
                     WHERE #root#.parent_id = parent.id
                     AND #root#.parent_id IS NOT NULL
                     AND #root#.version_id = parent.version_id
                     AND #root#.tenant_id = parent.tenant_id
                     AND #root#.version_id = :version
                     AND #root#.tenant_id = :tenant
                     AND #root#.id IN (:ids)'
                ),
                ['version' => $versionId, 'tenant' => $tenantId, 'ids' => $bytes],
                ['version' => \PDO::PARAM_STR, 'tenant' => \PDO::PARAM_STR, 'ids' => Connection::PARAM_STR_ARRAY]
            );

            $this->connection->executeUpdate(
                str_replace(
                    array_keys($parameters),
                    array_values($parameters),
                    'UPDATE #root# AS #root#
                     SET #root#.#property# = #root#.#field#
                     WHERE #root#.parent_id IS NULL
                     AND #root#.version_id = :version
                     AND #root#.tenant_id = :tenant
                     AND #root#.id IN (:ids)'
                ),
                ['version' => $versionId, 'tenant' => $tenantId, 'ids' => $bytes],
                ['version' => \PDO::PARAM_STR, 'tenant' => \PDO::PARAM_STR, 'ids' => Connection::PARAM_STR_ARRAY]
            );
        }
    }

    private function createIterator(string $tenantId, $entity): LastIdQuery
    {
        $escaped = EntityDefinitionQueryHelper::escape($entity);

        $query = $this->connection->createQueryBuilder();
        $query->select([$escaped . '.auto_increment', $escaped . '.id']);
        $query->from($escaped);
        $query->andWhere($escaped . '.tenant_id = :tenantId');
        $query->andWhere($escaped . '.auto_increment > :lastId');
        $query->addOrderBy($escaped . '.auto_increment');

        $query->setMaxResults(50);

        $query->setParameter('tenantId', Uuid::fromHexToBytes($tenantId));
        $query->setParameter('lastId', 0);

        return new LastIdQuery($query);
    }
}
