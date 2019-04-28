<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Dbal\Indexing;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\IteratorFactory;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityDefinitionQueryHelper;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Inherited;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToManyIdField;
use Shopware\Core\Framework\Event\ProgressAdvancedEvent;
use Shopware\Core\Framework\Event\ProgressFinishedEvent;
use Shopware\Core\Framework\Event\ProgressStartedEvent;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class ManyToManyIdFieldIndexer implements IndexerInterface
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
     * @var IteratorFactory
     */
    private $iteratorFactory;
    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    public function __construct(Connection $connection, DefinitionRegistry $registry, IteratorFactory $iteratorFactory, EventDispatcherInterface $eventDispatcher)
    {
        $this->connection = $connection;
        $this->registry = $registry;
        $this->iteratorFactory = $iteratorFactory;
        $this->eventDispatcher = $eventDispatcher;
    }

    public function index(\DateTimeInterface $timestamp): void
    {
        $context = Context::createDefaultContext();
        foreach ($this->registry->getDefinitions() as $definition) {
            $fields = $definition::getFields()->filterInstance(ManyToManyIdField::class);

            if ($fields->count() <= 0) {
                continue;
            }

            $iterator = $this->iteratorFactory->createIterator($definition);

            $this->eventDispatcher->dispatch(
                ProgressStartedEvent::NAME,
                new ProgressStartedEvent(
                    sprintf('Start indexing many to many ids for entity %s', $definition::getEntityName()),
                    $iterator->fetchCount()
                )
            );

            while ($ids = $iterator->fetch()) {
                $this->update($definition, $ids, $context);

                $this->eventDispatcher->dispatch(
                    ProgressAdvancedEvent::NAME,
                    new ProgressAdvancedEvent(\count($ids))
                );
            }

            $this->eventDispatcher->dispatch(
                ProgressFinishedEvent::NAME,
                new ProgressFinishedEvent(sprintf('Finished indexing many to many ids for entity %s', $definition::getEntityName()))
            );
        }
    }

    public function refresh(EntityWrittenContainerEvent $event): void
    {
        $written = $event->getEvents();
        if (!$written) {
            return;
        }

        /** @var EntityWrittenEvent $nested */
        foreach ($written as $nested) {
            $this->update($nested->getDefinition(), $nested->getIds(), $nested->getContext());
        }
    }

    private function update(string $definition, array $ids, Context $context)
    {
        if (empty($ids)) {
            return;
        }

        $fields = $definition::getFields()->filterInstance(ManyToManyIdField::class);

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

        $bytes = array_map(function ($id) {
            return Uuid::fromHexToBytes($id);
        }, $ids);

        /** @var ManyToManyIdField $field */
        foreach ($fields as $field) {
            $association = $definition::getFields()->get($field->getAssociationName());

            if (!$association) {
                throw new \RuntimeException(sprintf('Can not find association by property name %s', $field->getAssociationName()));
            }
            $parameters = ['ids' => $bytes];

            $replacement = [
                '#table#' => EntityDefinitionQueryHelper::escape($definition::getEntityName()),
                '#storage_name#' => EntityDefinitionQueryHelper::escape($field->getStorageName()),
                '#mapping_table#' => EntityDefinitionQueryHelper::escape($association->getMappingDefinition()::getEntityName()),
                '#reference_column#' => EntityDefinitionQueryHelper::escape($association->getMappingReferenceColumn()),
                '#mapping_column#' => EntityDefinitionQueryHelper::escape($association->getMappingLocalColumn()),
                '#join_column#' => EntityDefinitionQueryHelper::escape('id'),
            ];

            if ($definition::isInheritanceAware() && $association->is(Inherited::class)) {
                $replacement['#join_column#'] = $association->getPropertyName();
            }
            $versionCondition = '';
            if ($definition::isVersionAware()) {
                $versionCondition = 'AND #table#.version_id = #mapping_table#.#unescaped_table#_version_id AND #table#.version_id = :version';

                $parameters['version'] = Uuid::fromHexToBytes($context->getVersionId());
                $replacement['#unescaped_table#'] = $definition::getEntityName();
            }

            $tableTemplate = str_replace('#version_aware#', $versionCondition, $template);

            $sql = str_replace(
                array_keys($replacement),
                array_values($replacement),
                $tableTemplate
            );

            $this->connection->executeUpdate(
                $sql,
                $parameters,
                ['ids' => Connection::PARAM_STR_ARRAY]
            );
        }
    }
}
