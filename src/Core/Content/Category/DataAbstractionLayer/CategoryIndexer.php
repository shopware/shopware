<?php declare(strict_types=1);

namespace Shopware\Core\Content\Category\DataAbstractionLayer;

use Doctrine\DBAL\Connection;
use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Content\Category\Event\CategoryIndexerEvent;
use Shopware\Core\Framework\Adapter\Cache\CacheClearer;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\IteratorFactory;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\ChildCountUpdater;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexer;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexingMessage;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\TreeUpdater;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class CategoryIndexer extends EntityIndexer
{
    /**
     * @var IteratorFactory
     */
    private $iteratorFactory;

    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var EntityRepositoryInterface
     */
    private $repository;

    /**
     * @var ChildCountUpdater
     */
    private $childCountUpdater;

    /**
     * @var TreeUpdater
     */
    private $treeUpdater;

    /**
     * @var CategoryBreadcrumbUpdater
     */
    private $breadcrumbUpdater;

    /**
     * @var CacheClearer
     */
    private $cacheClearer;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    public function __construct(
        Connection $connection,
        IteratorFactory $iteratorFactory,
        EntityRepositoryInterface $repository,
        ChildCountUpdater $childCountUpdater,
        TreeUpdater $treeUpdater,
        CategoryBreadcrumbUpdater $breadcrumbUpdater,
        CacheClearer $cacheClearer,
        EventDispatcherInterface $eventDispatcher
    ) {
        $this->iteratorFactory = $iteratorFactory;
        $this->repository = $repository;
        $this->childCountUpdater = $childCountUpdater;
        $this->treeUpdater = $treeUpdater;
        $this->breadcrumbUpdater = $breadcrumbUpdater;
        $this->connection = $connection;
        $this->cacheClearer = $cacheClearer;
        $this->eventDispatcher = $eventDispatcher;
    }

    public function getName(): string
    {
        return 'category.indexer';
    }

    public function iterate($offset): ?EntityIndexingMessage
    {
        $iterator = $this->iteratorFactory->createIterator($this->repository->getDefinition(), $offset);

        $ids = $iterator->fetch();

        if (empty($ids)) {
            return null;
        }

        return new CategoryIndexingMessage(\array_values($ids), $iterator->getOffset());
    }

    public function update(EntityWrittenContainerEvent $event): ?EntityIndexingMessage
    {
        $categoryEvent = $event->getEventByEntityName(CategoryDefinition::ENTITY_NAME);

        if (!$categoryEvent) {
            return null;
        }

        $ids = $categoryEvent->getIds();
        foreach ($categoryEvent->getWriteResults() as $result) {
            if (!$result->getExistence()) {
                continue;
            }
            $state = $result->getExistence()->getState();

            if (isset($state['parent_id'])) {
                $ids[] = Uuid::fromBytesToHex($state['parent_id']);
            }

            $payload = $result->getPayload();
            if (isset($payload['parentId'])) {
                $ids[] = $payload['parentId'];
            }
        }

        if (empty($ids)) {
            return null;
        }

        // tree should be updated immediately
        foreach ($ids as $id) {
            $this->treeUpdater->update($id, CategoryDefinition::ENTITY_NAME, $event->getContext());
        }

        $children = $this->fetchChildren($ids, $event->getContext()->getVersionId());

        $ids = \array_unique(\array_merge($ids, $children));

        return new CategoryIndexingMessage(\array_values($ids), null, $event->getContext(), \count($ids) > 20);
    }

    public function handle(EntityIndexingMessage $message): void
    {
        $ids = $message->getData();

        $ids = \array_unique(\array_filter($ids));
        if (empty($ids)) {
            return;
        }

        $context = Context::createDefaultContext();

        $this->connection->beginTransaction();

        // listen to parent id changes
        $this->childCountUpdater->update(CategoryDefinition::ENTITY_NAME, $ids, $context);

        foreach ($ids as $id) {
            // listen to parent id changes
            $this->treeUpdater->update($id, CategoryDefinition::ENTITY_NAME, $context);
        }

        // listen to name changes
        $this->breadcrumbUpdater->update($ids, $context);

        $this->connection->commit();

        $this->eventDispatcher->dispatch(new CategoryIndexerEvent($ids, $context));

        $this->cacheClearer->invalidateIds($ids, CategoryDefinition::ENTITY_NAME);
    }

    private function fetchChildren(array $categoryIds, string $versionId): array
    {
        $query = $this->connection->createQueryBuilder();
        $query->select('DISTINCT LOWER(HEX(category.id))');
        $query->from('category');

        $wheres = [];
        foreach ($categoryIds as $id) {
            $key = 'path' . $id;
            $wheres[] = 'category.path LIKE :' . $key;
            $query->setParameter($key, '%|' . $id . '|%');
        }

        $query->andWhere('(' . \implode(' OR ', $wheres) . ')');
        $query->andWhere('category.version_id = :version');
        $query->setParameter('version', Uuid::fromHexToBytes($versionId));

        return $query->execute()->fetchAll(\PDO::FETCH_COLUMN);
    }
}
