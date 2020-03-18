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
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Inherited;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToManyIdField;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\IndexerInterface;
use Shopware\Core\Framework\DataAbstractionLayer\MappingEntityDefinition;
use Shopware\Core\Framework\Event\ProgressAdvancedEvent;
use Shopware\Core\Framework\Event\ProgressFinishedEvent;
use Shopware\Core\Framework\Event\ProgressStartedEvent;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * @deprecated tag:v6.3.0 - Use \Shopware\Core\Framework\DataAbstractionLayer\Indexing\ManyToManyIdFieldUpdater instead
 */
class ManyToManyIdFieldIndexer implements IndexerInterface
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
     * @var IteratorFactory
     */
    private $iteratorFactory;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    public function __construct(
        Connection $connection,
        DefinitionInstanceRegistry $registry,
        IteratorFactory $iteratorFactory,
        EventDispatcherInterface $eventDispatcher
    ) {
        $this->connection = $connection;
        $this->registry = $registry;
        $this->iteratorFactory = $iteratorFactory;
        $this->eventDispatcher = $eventDispatcher;
    }

    public function index(\DateTimeInterface $timestamp): void
    {
        $context = Context::createDefaultContext();

        foreach ($this->registry->getDefinitions() as $definition) {
            if (!$definition->hasManyToManyIdFields()) {
                continue;
            }

            $iterator = $this->iteratorFactory->createIterator($definition);

            $this->eventDispatcher->dispatch(
                new ProgressStartedEvent(
                    sprintf('Start indexing many to many ids for entity %s', $definition->getEntityName()),
                    $iterator->fetchCount()
                ),
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
                new ProgressFinishedEvent(sprintf('Finished indexing many to many ids for entity %s', $definition->getEntityName())),
                ProgressFinishedEvent::NAME
            );
        }
    }

    public function partial(?array $lastId, \DateTimeInterface $timestamp): ?array
    {
        $context = Context::createDefaultContext();

        $dataOffset = null;
        $definitionOffset = 0;

        if ($lastId) {
            $dataOffset = $lastId['dataOffset'];
            $definitionOffset = $lastId['definitionOffset'];
        }

        $definitions = array_values(array_filter(
            $this->registry->getDefinitions(),
            function (EntityDefinition $definition) {
                return $definition->hasManyToManyIdFields();
            }
        ));

        if (!isset($definitions[$definitionOffset])) {
            return null;
        }

        $definition = $definitions[$definitionOffset];

        $iterator = $this->iteratorFactory->createIterator($definition, $dataOffset);

        $ids = $iterator->fetch();

        if (empty($ids)) {
            ++$definitionOffset;

            return [
                'dataOffset' => null,
                'definitionOffset' => $definitionOffset,
            ];
        }

        $this->update($definition, $ids, $context);

        return [
            'dataOffset' => $iterator->getOffset(),
            'definitionOffset' => $definitionOffset,
        ];
    }

    public function refresh(EntityWrittenContainerEvent $event): void
    {
        $written = $event->getEvents();
        if (!$written) {
            return;
        }

        /** @var EntityWrittenEvent $nested */
        foreach ($written as $nested) {
            $definition = $this->registry->getByEntityName($nested->getEntityName());
            $this->update($definition, $nested->getIds(), $nested->getContext());
        }
    }

    public static function getName(): string
    {
        return 'Swag.ManyToManyIdFieldIndexer';
    }

    private function update(EntityDefinition $definition, array $ids, Context $context): void
    {
        if (empty($ids)) {
            return;
        }

        if ($definition instanceof MappingEntityDefinition) {
            $fkFields = $definition->getFields()->filterInstance(FkField::class);

            /** @var FkField $field */
            foreach ($fkFields as $field) {
                $foreignKeys = array_column($ids, $field->getPropertyName());
                $this->update($field->getReferenceDefinition(), $foreignKeys, $context);
            }

            return;
        }

        if (!$definition->hasManyToManyIdFields()) {
            return;
        }

        $fields = $definition->getFields()->filterInstance(ManyToManyIdField::class);

        if ($fields->count() <= 0) {
            return;
        }

        $template = <<<SQL
UPDATE #table#, #mapping_table# SET #table#.#storage_name# = (
    SELECT CONCAT('[', GROUP_CONCAT(JSON_QUOTE(LOWER(HEX(#mapping_table#.#reference_column#)))), ']')
    FROM #mapping_table#
    WHERE #mapping_table#.#mapping_column# = #table#.#join_column#
    #version_aware#
)
WHERE #mapping_table#.#mapping_column# = #table#.#join_column#
AND #table#.id IN (:ids)
#version_aware#
SQL;

        $resetTemplate = <<<SQL
UPDATE #table# SET #table#.#storage_name# = NULL
WHERE #table#.id IN (:ids)
SQL;

        if ($definition->isVersionAware()) {
            $resetTemplate .= ' AND #table#.version_id = :version';
        }

        $bytes = array_map(function ($id) {
            return Uuid::fromHexToBytes($id);
        }, $ids);

        /** @var ManyToManyIdField $field */
        foreach ($fields as $field) {
            /** @var ManyToManyAssociationField $association */
            $association = $definition->getFields()->get($field->getAssociationName());

            if (!$association instanceof ManyToManyAssociationField) {
                throw new \RuntimeException(sprintf('Can not find association by property name %s', $field->getAssociationName()));
            }
            $parameters = ['ids' => $bytes];

            $replacement = [
                '#table#' => EntityDefinitionQueryHelper::escape($definition->getEntityName()),
                '#storage_name#' => EntityDefinitionQueryHelper::escape($field->getStorageName()),
                '#mapping_table#' => EntityDefinitionQueryHelper::escape($association->getMappingDefinition()->getEntityName()),
                '#reference_column#' => EntityDefinitionQueryHelper::escape($association->getMappingReferenceColumn()),
                '#mapping_column#' => EntityDefinitionQueryHelper::escape($association->getMappingLocalColumn()),
                '#join_column#' => EntityDefinitionQueryHelper::escape('id'),
            ];

            if ($definition->isInheritanceAware() && $association->is(Inherited::class)) {
                $replacement['#join_column#'] = EntityDefinitionQueryHelper::escape($association->getPropertyName());
            }
            $versionCondition = '';
            if ($definition->isVersionAware()) {
                $versionCondition = 'AND #table#.version_id = #mapping_table#.#unescaped_table#_version_id AND #table#.version_id = :version';

                $parameters['version'] = Uuid::fromHexToBytes($context->getVersionId());
                $replacement['#unescaped_table#'] = $definition->getEntityName();
            }

            $tableTemplate = str_replace('#version_aware#', $versionCondition, $template);

            $sql = str_replace(
                array_keys($replacement),
                array_values($replacement),
                $tableTemplate
            );

            $resetSql = str_replace(
                array_keys($replacement),
                array_values($replacement),
                $resetTemplate
            );

            $this->connection->executeUpdate(
                $resetSql,
                $parameters,
                ['ids' => Connection::PARAM_STR_ARRAY]
            );

            $this->connection->executeUpdate(
                $sql,
                $parameters,
                ['ids' => Connection::PARAM_STR_ARRAY]
            );
        }
    }
}
