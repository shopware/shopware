<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Dbal\Indexing;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\FetchMode;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\IterableQuery;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\LastIdQuery;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\OffsetQuery;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\Event\ProgressAdvancedEvent;
use Shopware\Core\Framework\Event\ProgressFinishedEvent;
use Shopware\Core\Framework\Event\ProgressStartedEvent;
use Shopware\Core\Framework\Struct\Uuid;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class ChildCountIndexer implements IndexerInterface
{
    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * @var DefinitionRegistry
     */
    private $definitionRegistry;

    public function __construct(
        Connection $connection,
        EventDispatcherInterface $eventDispatcher,
        DefinitionRegistry $definitionRegistry
    ) {
        $this->connection = $connection;
        $this->eventDispatcher = $eventDispatcher;
        $this->definitionRegistry = $definitionRegistry;
    }

    public function index(\DateTime $timestamp): void
    {
        $context = Context::createDefaultContext();

        /** @var EntityDefinition|string $definition */
        foreach ($this->definitionRegistry->getElements() as $definition) {
            if (!$definition::isChildrenAware() || !$definition::isChildCountAware()) {
                continue;
            }

            $entityName = $definition::getEntityName();
            $iterator = $this->createIterator($entityName, $definition);

            $this->eventDispatcher->dispatch(
                ProgressStartedEvent::NAME,
                new ProgressStartedEvent('Start indexing child counts of ' . $entityName, $iterator->fetchCount())
            );

            while ($ids = $iterator->fetch()) {
                $ids = array_map(function ($id) {
                    return Uuid::fromBytesToHex($id);
                }, $ids);

                $this->updateChildCount($entityName, $ids, $definition::isVersionAware(), $context);

                $this->eventDispatcher->dispatch(
                    ProgressAdvancedEvent::NAME,
                    new ProgressAdvancedEvent(\count($ids))
                );
            }

            $this->eventDispatcher->dispatch(
                ProgressFinishedEvent::NAME,
                new ProgressFinishedEvent('Finished indexing child count of ' . $entityName)
            );
        }
    }

    public function refresh(EntityWrittenContainerEvent $event): void
    {
        /** @var EntityWrittenEvent $nested */
        foreach ($event->getEvents() as $nested) {
            $definition = $nested->getDefinition();

            if ($definition::isChildrenAware() && $definition::isChildCountAware()) {
                $this->update($nested, $nested->getIds(), $nested->getContext());
            }
        }
    }

    private function update(EntityWrittenEvent $event, array $ids, Context $context): void
    {
        $entityParents = array_map(function (EntityExistence $existence) {
            if (!array_key_exists('parent_id', $existence->getState()) || !$existence->getState()['parent_id']) {
                return null;
            }

            return Uuid::fromBytesToHex($existence->getState()['parent_id']);
        }, $event->getExistences());

        $entityName = $event->getDefinition()::getEntityName();

        $parentIds = $this->fetchParentIds($entityName, $ids, $event->getDefinition()::isVersionAware(), $context);
        $parentIds = array_keys(array_flip(array_filter(array_merge($entityParents, $parentIds))));

        $this->updateChildCount($entityName, $parentIds, $event->getDefinition()::isVersionAware(), $context);
    }

    private function updateChildCount(string $entityName, array $parentIds, bool $versionAware, Context $context): void
    {
        if (empty($parentIds)) {
            return;
        }

        $versionId = Uuid::fromStringToBytes($context->getVersionId());
        $parentIds = array_map(function ($id) {
            return Uuid::fromHexToBytes($id);
        }, $parentIds);

        $this->validateTableName($entityName);

        $sql = sprintf(
            'UPDATE #entity#  as parent
                LEFT JOIN
                (
                    SELECT parent_id, count(id) total
                    FROM   #entity#
                    %s
                    GROUP BY parent_id
                ) child ON parent.id = child.parent_id
            SET parent.child_count = IFNULL(child.total, 0)
            WHERE parent.id IN (:ids)
            %s',
            $versionAware ? 'WHERE version_id = :version' : '',
            $versionAware ? 'AND parent.version_id = :version' : ''
        );

        $sql = str_replace(
            ['#entity#'],
            [$entityName],
            $sql
        );

        $params = ['ids' => $parentIds];

        if ($versionAware) {
            $params['version'] = $versionId;
        }

        $this->connection->executeQuery(
            $sql,
            $params,
            ['ids' => Connection::PARAM_STR_ARRAY]
        );
    }

    private function fetchParentIds(string $entityName, array $ids, bool $versionAware, Context $context): array
    {
        $ids = array_map(function ($id) {
            return Uuid::fromStringToBytes($id);
        }, $ids);

        $this->validateTableName($entityName);

        $query = $this->connection->createQueryBuilder();
        $query->select(['parent_id']);
        $query->from($entityName);
        $query->andWhere('id IN (:ids)');

        $query->setParameter('ids', $ids, Connection::PARAM_STR_ARRAY);

        if ($versionAware) {
            $query->andWhere('version_id = :version');
            $query->setParameter('version', Uuid::fromStringToBytes($context->getVersionId()));
        }

        $parents = $query->execute()->fetchAll(FetchMode::COLUMN);
        $parents = array_filter($parents);

        return array_map(function (string $id) {
            return Uuid::fromBytesToHex($id);
        }, $parents);
    }

    private function createIterator(string $entityName, string $definition): IterableQuery
    {
        $query = $this->connection->createQueryBuilder();

        $query->from($entityName);

        $query->setMaxResults(50);

        /** @var EntityDefinition|string $definition */
        if ($definition::getFields()->has('autoIncrement')) {
            $query->select(['auto_increment', 'id']);
            $query->andWhere('auto_increment > :lastId');
            $query->addOrderBy('auto_increment');
            $query->setParameter('lastId', 0);

            return new LastIdQuery($query);
        }
        $query->select('id', 'id AS entityId');

        return new OffsetQuery($query);
    }

    private function validateTableName(string $tableName): void
    {
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $tableName)) {
            throw new \Exception(sprintf('Invalid table name %s', $tableName));
        }
    }
}
