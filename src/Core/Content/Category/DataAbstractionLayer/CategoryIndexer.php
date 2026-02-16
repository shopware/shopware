<?php declare(strict_types=1);

namespace Shopware\Core\Content\Category\DataAbstractionLayer;

use Doctrine\DBAL\Connection;
use Shopware\Core\Content\Category\Aggregate\CategoryTranslation\CategoryTranslationDefinition;
use Shopware\Core\Content\Category\CategoryCollection;
use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Content\Category\Event\CategoryIndexerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\IterableQuery;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\IteratorFactory;
use Shopware\Core\Framework\DataAbstractionLayer\Doctrine\RetryableTransaction;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\ChildCountUpdater;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexer;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexerRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexingMessage;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\TreeUpdater;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[Package('discovery')]
class CategoryIndexer extends EntityIndexer
{
    final public const CHILD_COUNT_UPDATER = 'category.child-count';
    final public const TREE_UPDATER = 'category.tree';
    final public const BREADCRUMB_UPDATER = 'category.breadcrumb';
    private const UPDATE_IDS_CHUNK_SIZE = 50;

    /**
     * @internal
     *
     * @param EntityRepository<CategoryCollection> $repository
     */
    public function __construct(
        private readonly Connection $connection,
        private readonly IteratorFactory $iteratorFactory,
        private readonly EntityRepository $repository,
        private readonly ChildCountUpdater $childCountUpdater,
        private readonly TreeUpdater $treeUpdater,
        private readonly CategoryBreadcrumbUpdater $breadcrumbUpdater,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly MessageBusInterface $messageBus,
    ) {
    }

    public function getName(): string
    {
        return 'category.indexer';
    }

    public function getTotal(): int
    {
        return $this->getIterator(null)->fetchCount();
    }

    public function iterate(?array $offset): ?EntityIndexingMessage
    {
        $iterator = $this->getIterator($offset);

        $ids = $iterator->fetch();

        if ($ids === []) {
            return null;
        }

        return new CategoryIndexingMessage(
            data: array_values($ids),
            offset: $iterator->getOffset()
        );
    }

    public function update(EntityWrittenContainerEvent $event): ?EntityIndexingMessage
    {
        $categoryEvent = $event->getEventByEntityName(CategoryDefinition::ENTITY_NAME);

        if (!$categoryEvent) {
            return null;
        }

        $ids = $categoryEvent->getIds();
        $idsWithChangedParentIds = [];
        $runAllUpdaters = false;
        $parentIdChanged = false;

        foreach ($categoryEvent->getWriteResults() as $result) {
            $operation = $result->getOperation();
            $payload = $result->getPayload();

            if ($operation === EntityWriteResult::OPERATION_INSERT || $operation === EntityWriteResult::OPERATION_DELETE) {
                $runAllUpdaters = true;
            }

            if (!$result->getExistence()) {
                continue;
            }
            $state = $result->getExistence()->getState();

            if (isset($state['parent_id'])) {
                $ids[] = Uuid::fromBytesToHex($state['parent_id']);
            }

            if (\array_key_exists('parentId', $payload)) {
                $parentIdChanged = true;
                if ($payload['parentId'] !== null) {
                    $ids[] = $payload['parentId'];
                }
                $idsWithChangedParentIds[] = $payload['id'];
            }
        }

        if ($ids === []) {
            return null;
        }

        $nameChanged = $runAllUpdaters || $this->hasNameChanged($event->getEventByEntityName(CategoryTranslationDefinition::ENTITY_NAME));

        if ($idsWithChangedParentIds !== []) {
            $this->treeUpdater->batchUpdate(
                $idsWithChangedParentIds,
                CategoryDefinition::ENTITY_NAME,
                $event->getContext(),
                true
            );
        }

        $children = $this->fetchChildren($ids, $event->getContext()->getVersionId());
        $ids = array_unique(array_merge($ids, $children));

        $chunks = \array_chunk($ids, self::UPDATE_IDS_CHUNK_SIZE);
        $idsForReturnedMessage = array_shift($chunks);

        $updatersSkips = $this->getSkipUpdaters($runAllUpdaters, $parentIdChanged, $nameChanged);

        foreach ($chunks as $chunk) {
            $childrenIndexingMessage = new CategoryIndexingMessage($chunk, null, $event->getContext());
            $childrenIndexingMessage->setIndexer($this->getName());
            $childrenIndexingMessage->addSkip(...$updatersSkips);
            EntityIndexerRegistry::addSkips($childrenIndexingMessage, $event->getContext());

            $this->messageBus->dispatch($childrenIndexingMessage);
        }

        $message = new CategoryIndexingMessage($idsForReturnedMessage, null, $event->getContext());
        $message->addSkip(...$updatersSkips);

        return $message;
    }

    public function handle(EntityIndexingMessage $message): void
    {
        $ids = $message->getData();
        if (!\is_array($ids)) {
            return;
        }

        $ids = array_values(array_unique(array_filter($ids)));
        if ($ids === []) {
            return;
        }

        $context = $message->getContext();

        RetryableTransaction::retryable($this->connection, function () use ($message, $ids, $context): void {
            if ($message->allow(self::CHILD_COUNT_UPDATER)) {
                // listen to parent id changes
                $this->childCountUpdater->update(CategoryDefinition::ENTITY_NAME, $ids, $context);
            }

            if ($message->allow(self::TREE_UPDATER)) {
                $this->treeUpdater->batchUpdate(
                    $ids,
                    CategoryDefinition::ENTITY_NAME,
                    $context,
                    !$message->isFullIndexing
                );
            }

            if ($message->allow(self::BREADCRUMB_UPDATER)) {
                // listen to name changes
                $this->breadcrumbUpdater->update($ids, $context);
            }
        });

        $this->eventDispatcher->dispatch(new CategoryIndexerEvent($ids, $context, $message->getSkip(), $message->isFullIndexing));
    }

    public function getOptions(): array
    {
        return [
            self::CHILD_COUNT_UPDATER,
            self::TREE_UPDATER,
            self::BREADCRUMB_UPDATER,
        ];
    }

    public function getDecorated(): EntityIndexer
    {
        throw new DecorationPatternException(static::class);
    }

    /**
     * @param array<string> $categoryIds
     *
     * @return array<string>
     */
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

        $query->andWhere('(' . implode(' OR ', $wheres) . ')');
        $query->andWhere('category.version_id = :version');
        $query->setParameter('version', Uuid::fromHexToBytes($versionId));

        return $query->executeQuery()->fetchFirstColumn();
    }

    /**
     * @param array{offset: int|null}|null $offset
     */
    private function getIterator(?array $offset): IterableQuery
    {
        return $this->iteratorFactory->createIterator($this->repository->getDefinition(), $offset);
    }

    /**
     * Checks if name changed in category_translation write results.
     */
    private function hasNameChanged(?EntityWrittenEvent $translationEvent): bool
    {
        if ($translationEvent === null) {
            return false;
        }

        foreach ($translationEvent->getWriteResults() as $result) {
            if (\array_key_exists('name', $result->getPayload())) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<string>
     */
    private function getSkipUpdaters(bool $runAllUpdaters, bool $parentIdChanged, bool $nameChanged): array
    {
        if ($runAllUpdaters) {
            return [];
        }

        $skipUpdaters = [];
        if (!$parentIdChanged) {
            $skipUpdaters[] = self::CHILD_COUNT_UPDATER;
            $skipUpdaters[] = self::TREE_UPDATER;
        }
        // Breadcrumb depends on both name (label) and parentId (path in tree)
        if (!$nameChanged && !$parentIdChanged) {
            $skipUpdaters[] = self::BREADCRUMB_UPDATER;
        }

        return $skipUpdaters;
    }
}
