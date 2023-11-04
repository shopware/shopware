<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\DataAbstractionLayer;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Shopware\Core\Content\Product\Events\ProductIndexerEvent;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\IterableQuery;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\IteratorFactory;
use Shopware\Core\Framework\DataAbstractionLayer\Doctrine\RetryableQuery;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\ChildCountUpdater;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexer;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexerRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexingMessage;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\InheritanceUpdater;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\ManyToManyIdFieldUpdater;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Profiling\Profiler;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[Package('core')]
class ProductIndexer extends EntityIndexer
{
    final public const INHERITANCE_UPDATER = 'product.inheritance';
    final public const STOCK_UPDATER = 'product.stock';
    final public const VARIANT_LISTING_UPDATER = 'product.variant-listing';
    final public const CHILD_COUNT_UPDATER = 'product.child-count';
    final public const MANY_TO_MANY_ID_FIELD_UPDATER = 'product.many-to-many-id-field';
    final public const CATEGORY_DENORMALIZER_UPDATER = 'product.category-denormalizer';
    final public const CHEAPEST_PRICE_UPDATER = 'product.cheapest-price';
    final public const RATING_AVERAGE_UPDATER = 'product.rating-average';
    final public const STREAM_UPDATER = 'product.stream';
    final public const SEARCH_KEYWORD_UPDATER = 'product.search-keyword';
    final public const STATES_UPDATER = 'product.states';

    /**
     * @internal
     */
    public function __construct(
        private readonly IteratorFactory $iteratorFactory,
        private readonly EntityRepository $repository,
        private readonly Connection $connection,
        private readonly VariantListingUpdater $variantListingUpdater,
        private readonly ProductCategoryDenormalizer $categoryDenormalizer,
        private readonly InheritanceUpdater $inheritanceUpdater,
        private readonly RatingAverageUpdater $ratingAverageUpdater,
        private readonly SearchKeywordUpdater $searchKeywordUpdater,
        private readonly ChildCountUpdater $childCountUpdater,
        private readonly ManyToManyIdFieldUpdater $manyToManyIdFieldUpdater,
        private readonly StockUpdater $stockUpdater,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly CheapestPriceUpdater $cheapestPriceUpdater,
        private readonly ProductStreamUpdater $streamUpdater,
        private readonly StatesUpdater $statesUpdater,
        private readonly MessageBusInterface $messageBus
    ) {
    }

    public function getName(): string
    {
        return 'product.indexer';
    }

    /**
     * @param array{offset: int|null}|null $offset
     */
    public function iterate(?array $offset): ?EntityIndexingMessage
    {
        $iterator = $this->getIterator($offset);

        $ids = $iterator->fetch();

        if (empty($ids)) {
            return null;
        }

        return new ProductIndexingMessage(array_values($ids), $iterator->getOffset());
    }

    public function update(EntityWrittenContainerEvent $event): ?EntityIndexingMessage
    {
        $updates = $event->getPrimaryKeys(ProductDefinition::ENTITY_NAME);

        if (empty($updates)) {
            return null;
        }

        Profiler::trace('product:indexer:inheritance', function () use ($updates, $event): void {
            $this->inheritanceUpdater->update(ProductDefinition::ENTITY_NAME, $updates, $event->getContext());
        });

        $stocks = $event->getPrimaryKeysWithPropertyChange(ProductDefinition::ENTITY_NAME, ['stock', 'isCloseout', 'minPurchase']);
        Profiler::trace('product:indexer:stock', function () use ($stocks, $event): void {
            $this->stockUpdater->update(array_values($stocks), $event->getContext());
        });

        $message = new ProductIndexingMessage(array_values($updates), null, $event->getContext());
        $message->addSkip(self::INHERITANCE_UPDATER, self::STOCK_UPDATER);

        $delayed = \array_unique(\array_filter(\array_merge(
            $this->getParentIds($updates),
            $this->getChildrenIds($updates)
        )));

        foreach (\array_chunk($delayed, 50) as $chunk) {
            $child = new ProductIndexingMessage($chunk, null, $event->getContext());
            $child->setIndexer($this->getName());
            EntityIndexerRegistry::addSkips($child, $event->getContext());

            $this->messageBus->dispatch($child);
        }

        return $message;
    }

    public function getTotal(): int
    {
        return $this->getIterator(null)->fetchCount();
    }

    public function getDecorated(): EntityIndexer
    {
        throw new DecorationPatternException(self::class);
    }

    public function handle(EntityIndexingMessage $message): void
    {
        $ids = array_values(array_unique(array_filter($message->getData())));

        if (empty($ids)) {
            return;
        }

        $parentIds = $this->filterVariants($ids);

        $context = $message->getContext();

        if ($message->allow(self::INHERITANCE_UPDATER)) {
            Profiler::trace('product:indexer:inheritance', function () use ($ids, $context): void {
                $this->inheritanceUpdater->update(ProductDefinition::ENTITY_NAME, $ids, $context);
            });
        }

        if ($message->allow(self::STOCK_UPDATER)) {
            Profiler::trace('product:indexer:stock', function () use ($ids, $context): void {
                $this->stockUpdater->update($ids, $context);
            });
        }

        if ($message->allow(self::VARIANT_LISTING_UPDATER)) {
            Profiler::trace('product:indexer:variant-listing', function () use ($parentIds, $context): void {
                $this->variantListingUpdater->update($parentIds, $context);
            });
        }

        if ($message->allow(self::CHILD_COUNT_UPDATER)) {
            Profiler::trace('product:indexer:child-count', function () use ($parentIds, $context): void {
                $this->childCountUpdater->update(ProductDefinition::ENTITY_NAME, $parentIds, $context);
            });
        }

        if ($message->allow(self::STREAM_UPDATER)) {
            Profiler::trace('product:indexer:streams', function () use ($ids, $context): void {
                $this->streamUpdater->updateProducts($ids, $context);
            });
        }

        if ($message->allow(self::MANY_TO_MANY_ID_FIELD_UPDATER)) {
            Profiler::trace('product:indexer:many-to-many', function () use ($ids, $context): void {
                $this->manyToManyIdFieldUpdater->update(ProductDefinition::ENTITY_NAME, $ids, $context);
            });
        }

        if ($message->allow(self::CATEGORY_DENORMALIZER_UPDATER)) {
            Profiler::trace('product:indexer:category', function () use ($ids, $context): void {
                $this->categoryDenormalizer->update($ids, $context);
            });
        }

        if ($message->allow(self::CHEAPEST_PRICE_UPDATER)) {
            Profiler::trace('product:indexer:cheapest-price', function () use ($parentIds, $context): void {
                $this->cheapestPriceUpdater->update($parentIds, $context);
            });
        }

        if ($message->allow(self::RATING_AVERAGE_UPDATER)) {
            Profiler::trace('product:indexer:rating', function () use ($parentIds, $context): void {
                $this->ratingAverageUpdater->update($parentIds, $context);
            });
        }

        if ($message->allow(self::SEARCH_KEYWORD_UPDATER)) {
            Profiler::trace('product:indexer:search-keywords', function () use ($ids, $context): void {
                $this->searchKeywordUpdater->update($ids, $context);
            });
        }

        if ($message->allow(self::STATES_UPDATER)) {
            Profiler::trace('product:indexer:states', function () use ($ids, $context): void {
                $this->statesUpdater->update($ids, $context);
            });
        }

        RetryableQuery::retryable($this->connection, function () use ($ids): void {
            $this->connection->executeStatement(
                'UPDATE product SET updated_at = :now WHERE id IN (:ids)',
                ['ids' => Uuid::fromHexToBytesList($ids), 'now' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)],
                ['ids' => ArrayParameterType::STRING]
            );
        });

        Profiler::trace('product:indexer:event', function () use ($ids, $context, $message): void {
            $this->eventDispatcher->dispatch(new ProductIndexerEvent($ids, $context, $message->getSkip()));
        });
    }

    /**
     * @return string[]
     */
    public function getOptions(): array
    {
        return [
            self::INHERITANCE_UPDATER,
            self::STOCK_UPDATER,
            self::VARIANT_LISTING_UPDATER,
            self::CHILD_COUNT_UPDATER,
            self::MANY_TO_MANY_ID_FIELD_UPDATER,
            self::CATEGORY_DENORMALIZER_UPDATER,
            self::CHEAPEST_PRICE_UPDATER,
            self::RATING_AVERAGE_UPDATER,
            self::STREAM_UPDATER,
            self::SEARCH_KEYWORD_UPDATER,
        ];
    }

    /**
     * @param array<string> $ids
     *
     * @return array<string>
     */
    private function getChildrenIds(array $ids): array
    {
        $childrenIds = $this->connection->fetchFirstColumn(
            'SELECT DISTINCT LOWER(HEX(id)) as id FROM product WHERE parent_id IN (:ids)',
            ['ids' => Uuid::fromHexToBytesList($ids)],
            ['ids' => ArrayParameterType::STRING]
        );

        return array_unique(array_filter($childrenIds));
    }

    /**
     * @param array<string> $ids
     *
     * @return string[]
     */
    private function getParentIds(array $ids): array
    {
        $parentIds = $this->connection->fetchFirstColumn(
            'SELECT DISTINCT LOWER(HEX(product.parent_id)) as id FROM product WHERE id IN (:ids)',
            ['ids' => Uuid::fromHexToBytesList($ids)],
            ['ids' => ArrayParameterType::STRING]
        );

        return array_unique(array_filter($parentIds));
    }

    /**
     * @param array<string> $ids
     *
     * @return array|mixed[]
     */
    private function filterVariants(array $ids): array
    {
        return $this->connection->fetchFirstColumn(
            'SELECT DISTINCT LOWER(HEX(`id`))
             FROM product
             WHERE `id` IN (:ids)
             AND `parent_id` IS NULL',
            ['ids' => Uuid::fromHexToBytesList($ids)],
            ['ids' => ArrayParameterType::STRING]
        );
    }

    /**
     * @param array{offset: int|null}|null $offset
     */
    private function getIterator(?array $offset): IterableQuery
    {
        return $this->iteratorFactory->createIterator($this->repository->getDefinition(), $offset);
    }
}
