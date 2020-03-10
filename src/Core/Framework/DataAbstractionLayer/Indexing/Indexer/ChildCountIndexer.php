<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Indexing\Indexer;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\FetchMode;
use Shopware\Core\Framework\Adapter\Cache\CacheClearer;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Cache\EntityCacheKeyGenerator;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\IteratorFactory;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityDefinitionQueryHelper;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\IndexerInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\Event\ProgressAdvancedEvent;
use Shopware\Core\Framework\Event\ProgressFinishedEvent;
use Shopware\Core\Framework\Event\ProgressStartedEvent;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * @deprecated tag:v6.3.0 - Use \Shopware\Core\Framework\DataAbstractionLayer\Indexing\ChildCountUpdater instead
 */
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
     * @var DefinitionInstanceRegistry
     */
    private $definitionRegistry;

    /**
     * @var EntityCacheKeyGenerator
     */
    private $cacheKeyGenerator;

    /**
     * @var CacheClearer
     */
    private $cache;

    /**
     * @var IteratorFactory
     */
    private $iteratorFactory;

    public function __construct(
        Connection $connection,
        EventDispatcherInterface $eventDispatcher,
        DefinitionInstanceRegistry $definitionRegistry,
        EntityCacheKeyGenerator $cacheKeyGenerator,
        CacheClearer $cache,
        IteratorFactory $iteratorFactory
    ) {
        $this->connection = $connection;
        $this->eventDispatcher = $eventDispatcher;
        $this->definitionRegistry = $definitionRegistry;
        $this->cacheKeyGenerator = $cacheKeyGenerator;
        $this->cache = $cache;
        $this->iteratorFactory = $iteratorFactory;
    }

    public function index(\DateTimeInterface $timestamp): void
    {
        $context = Context::createDefaultContext();

        foreach ($this->definitionRegistry->getDefinitions() as $definition) {
            if (!$definition->isChildrenAware() || !$definition->isChildCountAware()) {
                continue;
            }

            $entityName = $definition->getEntityName();
            $iterator = $this->iteratorFactory->createIterator($definition);

            $this->eventDispatcher->dispatch(
                new ProgressStartedEvent('Start indexing child counts of ' . $entityName, $iterator->fetchCount()),
                ProgressStartedEvent::NAME
            );

            while ($ids = $iterator->fetch()) {
                $this->updateChildCount($definition, $ids, $definition->isVersionAware(), $context);

                $this->eventDispatcher->dispatch(
                    new ProgressAdvancedEvent(\count($ids)),
                    ProgressAdvancedEvent::NAME
                );
            }

            $this->eventDispatcher->dispatch(
                new ProgressFinishedEvent('Finished indexing child count of ' . $entityName),
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
            $this->definitionRegistry->getDefinitions(),
            function (EntityDefinition $definition) {
                return $definition->isChildrenAware() && $definition->isChildCountAware();
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

        $this->updateChildCount($definition, $ids, $definition->isVersionAware(), $context);

        return [
            'dataOffset' => $iterator->getOffset(),
            'definitionOffset' => $definitionOffset,
        ];
    }

    public function refresh(EntityWrittenContainerEvent $event): void
    {
        /** @var EntityWrittenEvent $nested */
        foreach ($event->getEvents() as $nested) {
            $definition = $this->definitionRegistry->getByEntityName($nested->getEntityName());

            if ($definition->isChildrenAware() && $definition->isChildCountAware()) {
                $this->update($nested, $nested->getIds(), $nested->getContext());
            }
        }
    }

    public static function getName(): string
    {
        return 'Swag.ChildCountIndexer';
    }

    private function update(EntityWrittenEvent $event, array $ids, Context $context): void
    {
        $entityParents = array_map(function (EntityExistence $existence) {
            if (!array_key_exists('parent_id', $existence->getState()) || !$existence->getState()['parent_id']) {
                return null;
            }

            return Uuid::fromBytesToHex($existence->getState()['parent_id']);
        }, $event->getExistences());

        $entityName = $event->getEntityName();
        $definition = $this->definitionRegistry->getByEntityName($entityName);

        $parentIds = $this->fetchParentIds($entityName, $ids, $definition->isVersionAware(), $context);
        $parentIds = array_keys(array_flip(array_filter(array_merge($entityParents, $parentIds))));

        $this->updateChildCount($definition, $parentIds, $definition->isVersionAware(), $context);
    }

    private function updateChildCount(EntityDefinition $definition, array $parentIds, bool $versionAware, Context $context): void
    {
        $entityName = $definition->getEntityName();
        if (empty($parentIds)) {
            return;
        }

        $versionId = Uuid::fromHexToBytes($context->getVersionId());
        $bytes = array_map(function ($id) {
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
            [EntityDefinitionQueryHelper::escape($entityName)],
            $sql
        );

        $params = ['ids' => $bytes];

        if ($versionAware) {
            $params['version'] = $versionId;
        }

        $this->connection->executeUpdate(
            $sql,
            $params,
            ['ids' => Connection::PARAM_STR_ARRAY]
        );

        $tags = array_map(function ($id) use ($definition) {
            return $this->cacheKeyGenerator->getEntityTag($id, $definition->getEntityName());
        }, $parentIds);

        $this->cache->invalidateTags($tags);
    }

    private function fetchParentIds(string $entityName, array $ids, bool $versionAware, Context $context): array
    {
        $ids = array_map(function ($id) {
            return Uuid::fromHexToBytes($id);
        }, $ids);

        $this->validateTableName($entityName);

        $query = $this->connection->createQueryBuilder();
        $query->select(['parent_id']);
        $query->from($entityName);
        $query->andWhere('id IN (:ids)');

        $query->setParameter('ids', $ids, Connection::PARAM_STR_ARRAY);

        if ($versionAware) {
            $query->andWhere('version_id = :version');
            $query->setParameter('version', Uuid::fromHexToBytes($context->getVersionId()));
        }

        $parents = $query->execute()->fetchAll(FetchMode::COLUMN);
        $parents = array_filter($parents);

        return array_map(function (string $id) {
            return Uuid::fromBytesToHex($id);
        }, $parents);
    }

    private function validateTableName(string $tableName): void
    {
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $tableName)) {
            throw new \RuntimeException(sprintf('Invalid table name %s', $tableName));
        }
    }
}
