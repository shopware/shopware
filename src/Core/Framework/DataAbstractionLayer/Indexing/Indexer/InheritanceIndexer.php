<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Indexing\Indexer;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\IteratorFactory;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityDefinitionQueryHelper;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Field\AssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Inherited;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\IndexerInterface;
use Shopware\Core\Framework\Event\ProgressAdvancedEvent;
use Shopware\Core\Framework\Event\ProgressFinishedEvent;
use Shopware\Core\Framework\Event\ProgressStartedEvent;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class InheritanceIndexer implements IndexerInterface
{
    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var DefinitionInstanceRegistry
     */
    private $registry;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * @var IteratorFactory
     */
    private $iteratorFactory;

    public function __construct(Connection $connection, DefinitionInstanceRegistry $registry, EventDispatcherInterface $eventDispatcher, IteratorFactory $iteratorFactory)
    {
        $this->connection = $connection;
        $this->registry = $registry;
        $this->eventDispatcher = $eventDispatcher;
        $this->iteratorFactory = $iteratorFactory;
    }

    public function index(\DateTimeInterface $timestamp): void
    {
        $context = Context::createDefaultContext();

        foreach ($this->registry->getDefinitions() as $definition) {
            if (!$definition->isInheritanceAware()) {
                continue;
            }

            $iterator = $this->iteratorFactory->createIterator($definition);

            $this->eventDispatcher->dispatch(
                new ProgressStartedEvent('Start building inheritance for definition: ' . $definition->getEntityName(), $iterator->fetchCount()),
                ProgressStartedEvent::NAME
            );

            while ($ids = $iterator->fetch()) {
                $this->update($definition, $ids, $context);

                $this->eventDispatcher->dispatch(
                    new ProgressAdvancedEvent(\count($ids)),
                    ProgressAdvancedEvent::NAME
                );
            }

            $this->eventDispatcher->dispatch(
                new ProgressFinishedEvent('Finish building inheritance for definition: ' . $definition->getEntityName()),
                ProgressFinishedEvent::NAME
            );
        }
    }

    public function partial(?array $lastId, \DateTimeInterface $timestamp): ?array
    {
        $context = Context::createDefaultContext();

        $definitionOffset = 0;
        $dataOffset = null;

        if ($lastId) {
            $definitionOffset = $lastId['definitionOffset'];
            $dataOffset = $lastId['dataOffset'];
        }

        $definitions = array_filter(
            $this->registry->getDefinitions(),
            function (EntityDefinition $definition) {
                return $definition->isInheritanceAware();
            }
        );
        $definitions = array_values($definitions);

        if (!isset($definitions[$definitionOffset])) {
            return null;
        }

        $definition = $definitions[$definitionOffset];

        $iterator = $this->iteratorFactory->createIterator($definition, $dataOffset);

        $ids = $iterator->fetch();
        if (empty($ids)) {
            ++$definitionOffset;

            return [
                'definitionOffset' => $definitionOffset,
                'dataOffset' => null,
            ];
        }

        $this->update($definition, $ids, $context);

        return [
            'definitionOffset' => $definitionOffset,
            'dataOffset' => $iterator->getOffset(),
        ];
    }

    public function refresh(EntityWrittenContainerEvent $event): void
    {
        /** @var EntityWrittenEvent $nested */
        foreach ($event->getEvents() as $nested) {
            $definition = $this->registry->getByEntityName($nested->getEntityName());

            if ($definition->isInheritanceAware()) {
                $this->update($definition, $nested->getIds(), $nested->getContext());
            }
        }
    }

    public function update(EntityDefinition $definition, array $ids, Context $context): void
    {
        $inherited = $definition->getFields()->filter(function (Field $field) {
            return $field->is(Inherited::class) && $field instanceof AssociationField;
        });

        $associations = $inherited->filter(function (Field $field) {
            return $field instanceof OneToManyAssociationField || $field instanceof ManyToManyAssociationField || $field instanceof OneToOneAssociationField;
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

    public function updateToManyAssociations(EntityDefinition $definition, array $ids, FieldCollection $associations, Context $context): void
    {
        $bytes = array_map(function ($id) {
            return Uuid::fromHexToBytes($id);
        }, $ids);

        /** @var AssociationField $association */
        foreach ($associations as $association) {
            $reference = $association->getReferenceDefinition();

            $sql = sprintf(
                'UPDATE #root# SET #property# = IFNULL(
                        (
                            SELECT #reference#.#entity_id#
                            FROM   #reference#
                            WHERE  #reference#.#entity_id#         = #root#.id
                            %s
                            LIMIT 1
                        ),
                        IFNULL(#root#.parent_id, #root#.id)
                     )
                     WHERE #root#.id IN (:ids) OR #root#.parent_id IN (:ids)
                     %s',
                $definition->isVersionAware() ? 'AND #reference#.#entity_version_id# = #root#.version_id' : '',
                $definition->isVersionAware() ? 'AND #root#.version_id = :version' : ''
            );

            $parameters = [
                '#root#' => EntityDefinitionQueryHelper::escape($definition->getEntityName()),
                '#entity_id#' => EntityDefinitionQueryHelper::escape($definition->getEntityName() . '_id'),
                '#entity_version_id#' => EntityDefinitionQueryHelper::escape($definition->getEntityName() . '_version_id'),
                '#property#' => EntityDefinitionQueryHelper::escape($association->getPropertyName()),
                '#reference#' => EntityDefinitionQueryHelper::escape($reference->getEntityName()),
            ];

            $params = ['ids' => $bytes];

            if ($definition->isVersionAware()) {
                $versionId = Uuid::fromHexToBytes($context->getVersionId());
                $params['version'] = $versionId;
            }

            $this->connection->executeUpdate(
                str_replace(
                    array_keys($parameters),
                    array_values($parameters),
                    $sql
                ),
                $params,
                ['ids' => Connection::PARAM_STR_ARRAY]
            );
        }
    }

    public function updateToOneAssociations(EntityDefinition $definition, array $ids, FieldCollection $associations, Context $context): void
    {
        $bytes = array_map(function ($id) {
            return Uuid::fromHexToBytes($id);
        }, $ids);

        /** @var ManyToOneAssociationField $association */
        foreach ($associations as $association) {
            $parameters = [
                '#root#' => EntityDefinitionQueryHelper::escape($definition->getEntityName()),
                '#field#' => EntityDefinitionQueryHelper::escape($association->getStorageName()),
                '#property#' => EntityDefinitionQueryHelper::escape($association->getPropertyName()),
            ];

            $sql = 'UPDATE #root# as #root#, #root# as parent
                    SET #root#.#property# = IFNULL(#root#.#field#, parent.#field#)
                    WHERE #root#.parent_id = parent.id
                    AND #root#.parent_id IS NOT NULL
                    AND #root#.id IN (:ids)';

            $params = ['ids' => $bytes];

            if ($definition->isVersionAware()) {
                $sql .= 'AND #root#.version_id = parent.version_id
                         AND #root#.version_id = :version';
                $versionId = Uuid::fromHexToBytes($context->getVersionId());
                $params['version'] = $versionId;
            }

            $this->connection->executeUpdate(
                str_replace(
                    array_keys($parameters),
                    array_values($parameters),
                    $sql
                ),
                $params,
                ['ids' => Connection::PARAM_STR_ARRAY]
            );

            $sql = 'UPDATE #root# AS #root#
                    SET #root#.#property# = #root#.#field#
                    WHERE #root#.parent_id IS NULL
                    AND #root#.id IN (:ids)';

            $params = ['ids' => $bytes];

            if ($definition->isVersionAware()) {
                $sql .= 'AND #root#.version_id = :version';
                $versionId = Uuid::fromHexToBytes($context->getVersionId());
                $params['version'] = $versionId;
            }

            $this->connection->executeUpdate(
                str_replace(
                    array_keys($parameters),
                    array_values($parameters),
                    $sql
                ),
                $params,
                ['ids' => Connection::PARAM_STR_ARRAY]
            );
        }
    }

    public static function getName(): string
    {
        return 'Swag.InheritanceIndexer';
    }
}
