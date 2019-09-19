<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\DataAbstractionLayer\Indexing;

use Doctrine\DBAL\Connection;
use Shopware\Core\Content\Product\Aggregate\ProductReview\ProductReviewDefinition;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Cache\EntityCacheKeyGenerator;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\IteratorFactory;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\IndexerInterface;
use Shopware\Core\Framework\Event\ProgressAdvancedEvent;
use Shopware\Core\Framework\Event\ProgressFinishedEvent;
use Shopware\Core\Framework\Event\ProgressStartedEvent;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\Cache\Adapter\TagAwareAdapterInterface;
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
     * @var TagAwareAdapterInterface
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
        TagAwareAdapterInterface $cache,
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
        $context = Context::createDefaultContext();

        $iterator = $this->iteratorFactory->createIterator($this->productDefinition);

        $this->eventDispatcher->dispatch(
            new ProgressStartedEvent('Start indexing product rating average', $iterator->fetchCount()),
            ProgressStartedEvent::NAME
        );

        while ($ids = $iterator->fetch()) {
            $this->update($ids, $context);

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
        $context = Context::createDefaultContext();

        $iterator = $this->iteratorFactory->createIterator($this->productDefinition, $lastId);

        $ids = $iterator->fetch();
        if (empty($ids)) {
            return null;
        }

        $this->update($ids, $context);

        return $iterator->getOffset();
    }

    /**
     * this function checks if reviews havew been updated.
     * if so the associated products of the reviews have to be updated with the new score
     */
    public function refresh(EntityWrittenContainerEvent $event): void
    {
        $nested = $event->getEventByDefinition(ProductReviewDefinition::class);
        if ($nested) {
            $ids = $nested->getIds();
            $this->updateByReview($ids, $event->getContext());
        }
    }

    /**
     * this function is called when reviews have been updated
     * it looks up which products have to be calculated and calls the
     * update function
     */
    private function updateByReview(array $reviewIds, Context $context): void
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

        $productIds = Uuid::fromBytesToHexList(array_column($results, 'product_id'));

        $this->update($productIds, $context);
    }

    /**
     * this function is called with the ids of products
     * foreach product in this list the average score of reviews is calculated
     * the product cache for each updated product is invalidated
     */
    private function update(array $productIds, Context $context): void
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
)
WHERE (product.id IN (:ids) OR product.parent_id IN (:ids))
SQL;

        $productByteIds = Uuid::fromHexToBytesList($productIds);

        $this->connection->executeUpdate(
            $sql,
            ['ids' => $productByteIds],
            ['ids' => Connection::PARAM_STR_ARRAY]
        );

        $tags = [];
        foreach ($productIds as $productId) {
            $tags[] = $this->cacheKeyGenerator->getEntityTag($productId, $this->productDefinition);
        }
        $this->cache->invalidateTags($tags);
    }
}
