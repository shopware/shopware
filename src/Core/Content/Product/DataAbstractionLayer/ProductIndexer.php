<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\DataAbstractionLayer;

use Doctrine\DBAL\Connection;
use Shopware\Core\Content\Product\Events\ProductIndexerEvent;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\IteratorFactory;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\ChildCountUpdater;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexer;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexingMessage;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\InheritanceUpdater;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\ManyToManyIdFieldUpdater;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class ProductIndexer extends EntityIndexer
{
    private IteratorFactory $iteratorFactory;

    private EntityRepositoryInterface $repository;

    private Connection $connection;

    private VariantListingUpdater $variantListingUpdater;

    private ProductCategoryDenormalizer $categoryDenormalizer;

    private CheapestPriceUpdater $cheapestPriceUpdater;

    private SearchKeywordUpdater $searchKeywordUpdater;

    private InheritanceUpdater $inheritanceUpdater;

    private RatingAverageUpdater $ratingAverageUpdater;

    private ChildCountUpdater $childCountUpdater;

    private ManyToManyIdFieldUpdater $manyToManyIdFieldUpdater;

    private StockUpdater $stockUpdater;

    private EventDispatcherInterface $eventDispatcher;

    private ProductStreamUpdater $streamUpdater;

    public function __construct(
        IteratorFactory $iteratorFactory,
        EntityRepositoryInterface $repository,
        Connection $connection,
        VariantListingUpdater $variantListingUpdater,
        ProductCategoryDenormalizer $categoryDenormalizer,
        InheritanceUpdater $inheritanceUpdater,
        RatingAverageUpdater $ratingAverageUpdater,
        SearchKeywordUpdater $searchKeywordUpdater,
        ChildCountUpdater $childCountUpdater,
        ManyToManyIdFieldUpdater $manyToManyIdFieldUpdater,
        StockUpdater $stockUpdater,
        EventDispatcherInterface $eventDispatcher,
        CheapestPriceUpdater $cheapestPriceUpdater,
        ProductStreamUpdater $streamUpdater
    ) {
        $this->iteratorFactory = $iteratorFactory;
        $this->repository = $repository;
        $this->connection = $connection;
        $this->variantListingUpdater = $variantListingUpdater;
        $this->categoryDenormalizer = $categoryDenormalizer;
        $this->searchKeywordUpdater = $searchKeywordUpdater;
        $this->inheritanceUpdater = $inheritanceUpdater;
        $this->ratingAverageUpdater = $ratingAverageUpdater;
        $this->childCountUpdater = $childCountUpdater;
        $this->manyToManyIdFieldUpdater = $manyToManyIdFieldUpdater;
        $this->stockUpdater = $stockUpdater;
        $this->eventDispatcher = $eventDispatcher;
        $this->cheapestPriceUpdater = $cheapestPriceUpdater;
        $this->streamUpdater = $streamUpdater;
    }

    public function getName(): string
    {
        return 'product.indexer';
    }

    /**
     * @param array|null $offset
     *
     * @deprecated tag:v6.5.0 The parameter $offset will be native typed
     */
    public function iterate(/*?array */$offset): ?EntityIndexingMessage
    {
        $iterator = $this->iteratorFactory->createIterator($this->repository->getDefinition(), $offset);

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

        $this->inheritanceUpdater->update(ProductDefinition::ENTITY_NAME, $updates, $event->getContext());

        $this->stockUpdater->update($updates, $event->getContext());

        return new ProductIndexingMessage(array_values($updates), null, $event->getContext());
    }

    public function handle(EntityIndexingMessage $message): void
    {
        $ids = $message->getData();
        $ids = array_unique(array_filter($ids));

        if (empty($ids)) {
            return;
        }

        $parentIds = $this->getParentIds($ids);

        $childrenIds = $this->getChildrenIds($ids);

        $context = $message->getContext();

        $this->connection->beginTransaction();

        $all = array_filter(array_unique(array_merge($ids, $parentIds, $childrenIds)));

        $this->inheritanceUpdater->update(ProductDefinition::ENTITY_NAME, $all, $context);

        $this->stockUpdater->update($ids, $context);

        $this->variantListingUpdater->update($parentIds, $context);

        $this->childCountUpdater->update(ProductDefinition::ENTITY_NAME, $parentIds, $context);

        $this->manyToManyIdFieldUpdater->update(ProductDefinition::ENTITY_NAME, $ids, $context);

        $this->categoryDenormalizer->update($ids, $context);

        $this->cheapestPriceUpdater->update($parentIds, $context);

        $this->ratingAverageUpdater->update($parentIds, $context);

        $this->streamUpdater->updateProducts($all, $context);

        $this->searchKeywordUpdater->update(array_merge($ids, $childrenIds), $context);

        $this->connection->executeStatement(
            'UPDATE product SET updated_at = :now WHERE id IN (:ids)',
            ['ids' => Uuid::fromHexToBytesList($all), 'now' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)],
            ['ids' => Connection::PARAM_STR_ARRAY]
        );

        $this->connection->commit();

        $this->eventDispatcher->dispatch(new ProductIndexerEvent($ids, $childrenIds, $parentIds, $context));
    }

    private function getChildrenIds(array $ids): array
    {
        $childrenIds = $this->connection->fetchAll(
            'SELECT DISTINCT LOWER(HEX(id)) as id FROM product WHERE parent_id IN (:ids)',
            ['ids' => Uuid::fromHexToBytesList($ids)],
            ['ids' => Connection::PARAM_STR_ARRAY]
        );

        return array_unique(array_filter(array_column($childrenIds, 'id')));
    }

    /**
     * @return array|mixed[]
     */
    private function getParentIds(array $ids): array
    {
        $parentIds = $this->connection->fetchAll(
            'SELECT DISTINCT LOWER(HEX(IFNULL(product.parent_id, id))) as id FROM product WHERE id IN (:ids)',
            ['ids' => Uuid::fromHexToBytesList($ids)],
            ['ids' => Connection::PARAM_STR_ARRAY]
        );

        return array_unique(array_filter(array_column($parentIds, 'id')));
    }
}
