<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\DataAbstractionLayer;

use Doctrine\DBAL\Connection;
use Shopware\Core\Content\Product\DataAbstractionLayer\Indexing\ListingPriceUpdater;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\Adapter\Cache\CacheClearer;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\IteratorFactory;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\ChildCountUpdater;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexerInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexingMessage;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\InheritanceUpdater;
use Shopware\Core\Framework\Uuid\Uuid;

class ProductIndexer implements EntityIndexerInterface
{
    /**
     * @var IteratorFactory
     */
    private $iteratorFactory;

    /**
     * @var EntityRepositoryInterface
     */
    private $repository;

    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var CacheClearer
     */
    private $cacheClearer;

    /**
     * @var VariantListingUpdater
     */
    private $variantListingUpdater;

    /**
     * @var ProductCategoryDenormalizer
     */
    private $categoryDenormalizer;

    /**
     * @var ListingPriceUpdater
     */
    private $listingPriceUpdater;

    /**
     * @var SearchKeywordUpdater
     */
    private $searchKeywordUpdater;

    /**
     * @var InheritanceUpdater
     */
    private $inheritanceUpdater;

    /**
     * @var RatingAverageUpdater
     */
    private $ratingAverageUpdater;

    /**
     * @var ChildCountUpdater
     */
    private $childCountUpdater;

    public function __construct(
        IteratorFactory $iteratorFactory,
        EntityRepositoryInterface $repository,
        Connection $connection,
        CacheClearer $cacheClearer,
        VariantListingUpdater $variantListingUpdater,
        ProductCategoryDenormalizer $categoryDenormalizer,
        ListingPriceUpdater $listingPriceUpdater,
        InheritanceUpdater $inheritanceUpdater,
        RatingAverageUpdater $ratingAverageUpdater,
        SearchKeywordUpdater $searchKeywordUpdater,
        ChildCountUpdater $childCountUpdater
    ) {
        $this->iteratorFactory = $iteratorFactory;
        $this->repository = $repository;
        $this->connection = $connection;
        $this->cacheClearer = $cacheClearer;
        $this->variantListingUpdater = $variantListingUpdater;
        $this->categoryDenormalizer = $categoryDenormalizer;
        $this->listingPriceUpdater = $listingPriceUpdater;
        $this->searchKeywordUpdater = $searchKeywordUpdater;
        $this->inheritanceUpdater = $inheritanceUpdater;
        $this->ratingAverageUpdater = $ratingAverageUpdater;
        $this->childCountUpdater = $childCountUpdater;
    }

    public function getName(): string
    {
        return 'product.indexer';
    }

    public function iterate($offset): ?EntityIndexingMessage
    {
        $iterator = $this->iteratorFactory->createIterator($this->repository->getDefinition(), $offset);

        $ids = $iterator->fetch();

        if (empty($ids)) {
            return null;
        }

        return new EntityIndexingMessage($ids, $iterator->getOffset());
    }

    public function update(EntityWrittenContainerEvent $event): ?EntityIndexingMessage
    {
        $updates = $event->getPrimaryKeys(ProductDefinition::ENTITY_NAME);

        if (empty($updates)) {
            return null;
        }

        $this->inheritanceUpdater->update(ProductDefinition::ENTITY_NAME, $updates, $event->getContext());

        return new EntityIndexingMessage($updates, null);
    }

    public function handle(EntityIndexingMessage $message): void
    {
        $ids = $message->getData();

        if (empty($ids)) {
            return;
        }

        $parentIds = $this->getParentIds($ids);

        $childrenIds = $this->getChildrenIds($ids);

        $context = Context::createDefaultContext();

        $this->inheritanceUpdater->update(
            ProductDefinition::ENTITY_NAME,
            array_merge($ids, $parentIds, $childrenIds),
            $context
        );

        $this->variantListingUpdater->update($parentIds, $context);

        $this->childCountUpdater->update(ProductDefinition::ENTITY_NAME, $parentIds, $context);

        $this->categoryDenormalizer->update($ids, $context);

        $this->listingPriceUpdater->update($ids, $context);

        $this->ratingAverageUpdater->update($ids, $context);

        $this->searchKeywordUpdater->update($ids, $context);

        $this->cacheClearer->invalidateIds(
            array_merge($ids, $parentIds, $childrenIds),
            ProductDefinition::ENTITY_NAME
        );

//        $this->eventDispatcher->dispatch()
    }

    private function getChildrenIds(array $ids): array
    {
        $childrenIds = $this->connection->fetchAll(
            'SELECT DISTINCT LOWER(HEX(id)) as id FROM product WHERE parent_id IN (:ids)',
            ['ids' => Uuid::fromHexToBytesList($ids)],
            ['ids' => Connection::PARAM_STR_ARRAY]
        );

        return array_column($childrenIds, 'id');
    }

    /**
     * @return array|mixed[]
     */
    private function getParentIds(array $ids)
    {
        $parentIds = $this->connection->fetchAll(
            'SELECT DISTINCT LOWER(HEX(IFNULL(product.parent_id, id))) as id FROM product WHERE id IN (:ids)',
            ['ids' => Uuid::fromHexToBytesList($ids)],
            ['ids' => Connection::PARAM_STR_ARRAY]
        );
        $parentIds = array_column($parentIds, 'id');

        return $parentIds;
    }
}
