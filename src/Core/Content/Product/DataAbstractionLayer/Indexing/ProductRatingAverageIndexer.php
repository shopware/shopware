<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\DataAbstractionLayer\Indexing;

use Doctrine\DBAL\Connection;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\Adapter\Cache\CacheClearer;
use Shopware\Core\Framework\DataAbstractionLayer\Cache\EntityCacheKeyGenerator;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\IteratorFactory;
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
        $iterator->getQuery()->andWhere('product.parent_id IS NULL');

        $this->eventDispatcher->dispatch(
            new ProgressStartedEvent('Start indexing product rating average', $iterator->fetchCount()),
            ProgressStartedEvent::NAME
        );

        while ($ids = $iterator->fetch()) {
            $this->update($ids);

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
        $iterator->getQuery()->andWhere('product.parent_id IS NULL');

        $ids = $iterator->fetch();
        if (empty($ids)) {
            return null;
        }

        $this->update($ids);

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
        $nested = $event->getEventByEntityName(ProductDefinition::ENTITY_NAME);

        if ($nested) {
            $parentIds = $this->fetchParentIds($nested->getIds());
            $this->update($parentIds);
        }
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
    private function update(array $parentIds): void
    {
        if (empty($parentIds)) {
            return;
        }

        $this->connection->executeUpdate(
            'UPDATE product SET rating_average = NULL WHERE parent_id IN (:ids) OR id IN (:ids)',
            ['ids' => Uuid::fromHexToBytesList($parentIds)],
            ['ids' => Connection::PARAM_STR_ARRAY]
        );

        $query = $this->connection->createQueryBuilder();
        $query->select([
            'IFNULL(product.parent_id, product.id) as id',
            'AVG(product_review.points) as average',
        ]);
        $query->from('product_review');
        $query->leftJoin('product_review', 'product', 'product', 'product.id = product_review.product_id OR product.parent_id = product_review.product_id');
        $query->andWhere('product_review.status = 1');
        $query->andWhere('product.id IN (:ids) OR product.parent_id IN (:ids)');
        $query->setParameter('ids', Uuid::fromHexToBytesList($parentIds), Connection::PARAM_STR_ARRAY);
        $query->addGroupBy('IFNULL(product.parent_id, product.id)');

        $averages = $query->execute()->fetchAll();

        $this->connection->transactional(function () use ($averages): void {
            foreach ($averages as $average) {
                $this->connection->executeUpdate(
                    'UPDATE product SET rating_average = :average WHERE id = :id',
                    ['average' => $average['average'], 'id' => $average['id']]
                );
            }
        });

        $tags = [];
        foreach ($parentIds as $productId) {
            $tags[] = $this->cacheKeyGenerator->getEntityTag($productId, ProductDefinition::ENTITY_NAME);
        }
        $this->cache->invalidateTags($tags);
    }

    private function fetchParentIds(array $ids): array
    {
        $query = $this->connection->createQueryBuilder();
        $query->select('LOWER(HEX(IFNULL(product.parent_id, product.id))) as id');
        $query->from('product');
        $query->where('product.id IN (:ids)');
        $query->setParameter('ids', Uuid::fromHexToBytesList($ids), Connection::PARAM_STR_ARRAY);

        return $query->execute()->fetchAll(\PDO::FETCH_COLUMN);
    }
}
