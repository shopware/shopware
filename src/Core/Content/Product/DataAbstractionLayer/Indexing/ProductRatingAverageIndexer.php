<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\DataAbstractionLayer\Indexing;

use Doctrine\DBAL\Connection;
use Shopware\Core\Content\Product\Aggregate\ProductReview\ProductReviewDefinition;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\Adapter\Cache\CacheClearer;
use Shopware\Core\Framework\DataAbstractionLayer\Cache\EntityCacheKeyGenerator;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\IteratorFactory;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityDeletedEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\IndexerInterface;
use Shopware\Core\Framework\Event\ProgressAdvancedEvent;
use Shopware\Core\Framework\Event\ProgressFinishedEvent;
use Shopware\Core\Framework\Event\ProgressStartedEvent;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class ProductRatingAverageIndexer implements IndexerInterface
{
    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var IteratorFactory
     */
    private $iteratorFactory;

    /**
     * @var ProductDefinition
     */
    private $productDefinition;

    /**
     * @var CacheClearer
     */
    private $cache;

    /**
     * @var EntityCacheKeyGenerator
     */
    private $cacheKeyGenerator;

    public function __construct(
        EventDispatcherInterface $eventDispatcher,
        Connection $connection,
        IteratorFactory $iteratorFactory,
        ProductDefinition $productDefinition,
        CacheClearer $cache,
        EntityCacheKeyGenerator $cacheKeyGenerator
    ) {
        $this->eventDispatcher = $eventDispatcher;
        $this->connection = $connection;
        $this->iteratorFactory = $iteratorFactory;
        $this->productDefinition = $productDefinition;
        $this->cache = $cache;
        $this->cacheKeyGenerator = $cacheKeyGenerator;
    }

    /**
     * if indexing is called we want to calculate the average review/rating score for each product
     */
    public function index(\DateTimeInterface $timestamp): void
    {
        $iterator = $this->iteratorFactory->createIterator($this->productDefinition);

        $this->eventDispatcher->dispatch(
            new ProgressStartedEvent('Start indexing product rating average', $iterator->fetchCount()),
            ProgressStartedEvent::NAME
        );

        while ($ids = $iterator->fetch()) {
            $this->update(Uuid::fromHexToBytesList($ids), []);

            $this->eventDispatcher->dispatch(
                new ProgressAdvancedEvent(\count($ids)),
                ProgressAdvancedEvent::NAME
            );
        }

        $this->eventDispatcher->dispatch(
            new ProgressFinishedEvent('Finished indexing product rating average'),
            ProgressFinishedEvent::NAME
        );
    }

    public function partial(?array $lastId, \DateTimeInterface $timestamp): ?array
    {
        $iterator = $this->iteratorFactory->createIterator($this->productDefinition, $lastId);

        $ids = $iterator->fetch();
        if (empty($ids)) {
            return null;
        }

        $this->update(Uuid::fromHexToBytesList($ids), []);

        return $iterator->getOffset();
    }

    public static function getName(): string
    {
        return 'Swag.ProductRatingAverageIndexer';
    }

    /**
     * this function checks if reviews havew been updated.
     * if so the associated products of the reviews have to be updated with the new score
     */
    public function refresh(EntityWrittenContainerEvent $event): void
    {
        $nested = $event->getEventByEntityName(ProductReviewDefinition::ENTITY_NAME);

        if ($nested instanceof EntityDeletedEvent) {
            $nested = $event->getEventByEntityName(ProductDefinition::ENTITY_NAME);

            $this->update(Uuid::fromHexToBytesList($nested->getIds()), []);

            return;
        }

        if ($nested) {
            $this->updateByReview($nested->getIds());
        }
    }

    /**
     * this function is called when reviews have been updated
     * it looks up which products have to be calculated and calls the
     * update function
     */
    private function updateByReview(array $reviewIds): void
    {
        if (empty($reviewIds)) {
            return;
        }

        // select productids of all reviews that have been updated
        $sql = 'SELECT product_id FROM product_review WHERE product_review.id in (:ids)';

        $results = $this->connection->executeQuery(
            $sql,
            ['ids' => Uuid::fromHexToBytesList($reviewIds)],
            ['ids' => Connection::PARAM_STR_ARRAY]
        )->fetchAll();

        if (count($results) === 0) {
            return;
        }

        $productIds = array_column($results, 'product_id');

        $this->update($productIds, []);
    }

    /**
     * calculate product ratings
     * as difference to the normal indexer we haven't deleted the review and have to
     * exclude them from the calculation process.
     * They will be deleted and therefore may not taken in account for the rating
     * all ids in reviewIds will be ignored while calculating the product average
     * if reviews are deleted => as we are in pre delete context we have to ignore them
     * if reviews are updated => we have to consider them, take care that they are not
     */
    private function update(array $productIds, array $excludedReviewIds): void
    {
        if (empty($productIds)) {
            return;
        }

        $sql = <<<SQL
UPDATE product SET product.rating_average = (
    SELECT AVG(product_review.points)
    FROM product_review
    WHERE product_review.product_id = IFNULL(product.parent_id, product.id) 
    AND product_review.status = 1
SQL;
        $params = ['ids' => $productIds];
        $paramTypes = ['ids' => Connection::PARAM_STR_ARRAY];
        if (count($excludedReviewIds) > 0) {
            $sql .= ' AND product_review.id NOT IN (:reviewIds)';
            $params['reviewIds'] = $excludedReviewIds;
            $paramTypes['reviewIds'] = Connection::PARAM_STR_ARRAY;
        }
        $sql .= ') WHERE (product.id IN (:ids) OR product.parent_id IN (:ids))';

        $this->connection->executeUpdate($sql, $params, $paramTypes);

        $tags = [];
        foreach ($productIds as $productId) {
            $tags[] = $this->cacheKeyGenerator->getEntityTag(Uuid::fromBytesToHex($productId), $this->productDefinition);
        }
        $this->cache->invalidateTags($tags);
    }
}
