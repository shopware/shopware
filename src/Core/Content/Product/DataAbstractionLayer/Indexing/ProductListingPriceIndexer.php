<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\DataAbstractionLayer\Indexing;

use Doctrine\DBAL\Connection;
use Shopware\Core\Content\Product\Aggregate\ProductPrice\ProductPriceDefinition;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Cache\EntityCacheKeyGenerator;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\IteratorFactory;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\PriceRulesJsonFieldSerializer;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\IndexerInterface;
use Shopware\Core\Framework\Doctrine\FetchModeHelper;
use Shopware\Core\Framework\Event\ProgressAdvancedEvent;
use Shopware\Core\Framework\Event\ProgressFinishedEvent;
use Shopware\Core\Framework\Event\ProgressStartedEvent;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\Cache\Adapter\TagAwareAdapterInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class ProductListingPriceIndexer implements IndexerInterface
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
     * @var EntityCacheKeyGenerator
     */
    private $cacheKeyGenerator;

    /**
     * @var TagAwareAdapterInterface
     */
    private $cache;

    public function __construct(
        EventDispatcherInterface $eventDispatcher,
        Connection $connection,
        IteratorFactory $iteratorFactory,
        ProductDefinition $productDefinition,
        EntityCacheKeyGenerator $cacheKeyGenerator,
        TagAwareAdapterInterface $cache
    ) {
        $this->eventDispatcher = $eventDispatcher;
        $this->connection = $connection;
        $this->iteratorFactory = $iteratorFactory;
        $this->productDefinition = $productDefinition;
        $this->cacheKeyGenerator = $cacheKeyGenerator;
        $this->cache = $cache;
    }

    public function index(\DateTimeInterface $timestamp): void
    {
        $iterator = $this->iteratorFactory->createIterator($this->productDefinition);

        $this->eventDispatcher->dispatch(
            new ProgressStartedEvent('Start indexing listing prices', $iterator->fetchCount()),
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
            new ProgressFinishedEvent('Finished indexing listing prices'),
            ProgressFinishedEvent::NAME
        );
    }

    public function refresh(EntityWrittenContainerEvent $event): void
    {
        $prices = $event->getEventByDefinition(ProductPriceDefinition::class);
        if (!$prices) {
            return;
        }

        $ids = $this->fetchProductPriceIds($prices->getIds());

        $ids = array_values(array_keys(array_flip($ids)));

        $this->update($ids);
    }

    private function update(array $ids): void
    {
        if (empty($ids)) {
            return;
        }

        $prices = $this->fetchPrices($ids);

        $tags = [];

        foreach ($prices as $id => $productPrices) {
            $productPrices = $this->convertPrices($productPrices);
            $ruleIds = array_keys(array_flip(array_column($productPrices, 'ruleId')));
            $listingPrices = [];

            foreach ($ruleIds as $ruleId) {
                $listingPrices[] = $this->findCheapestRulePrice($productPrices, $ruleId);
            }

            $listingPrices = PriceRulesJsonFieldSerializer::convertToStorage($listingPrices);

            $this->connection->executeUpdate(
                'UPDATE product SET listing_prices = :price WHERE id = :id',
                [
                    'price' => json_encode($listingPrices),
                    'id' => Uuid::fromHexToBytes($id),
                ]
            );

            $tags[] = $this->cacheKeyGenerator->getEntityTag($id, $this->productDefinition);
        }

        $this->cache->invalidateTags($tags);
    }

    private function fetchPrices(array $ids): array
    {
        $query = $this->connection->createQueryBuilder();
        $query->select([
            'LOWER(HEX(IFNULL(product.parent_id, product.id))) as id',
            'price.id as price_id',
            'product.id as variant_id',
            'price.rule_id',
            'price.price',
            'price.currency_id',
        ]);

        $query->from('product', 'product');
        $query->innerJoin('product', 'product_price', 'price', 'price.product_id = product.id');
        $query->andWhere('product.id IN (:ids) OR product.parent_id IN (:ids)');
        $query->andWhere('price.quantity_end IS NULL');

        $ids = Uuid::fromHexToBytesList($ids);

        $query->setParameter('ids', $ids, Connection::PARAM_STR_ARRAY);

        $data = $query->execute()->fetchAll();

        return FetchModeHelper::group($data);
    }

    private function convertPrices(array $productPrices): array
    {
        $productPrices = array_map(
            function (array $price) {
                return [
                    'id' => bin2hex($price['price_id']),
                    'variantId' => bin2hex($price['variant_id']),
                    'ruleId' => bin2hex($price['rule_id']),
                    'currencyId' => bin2hex($price['currency_id']),
                    'price' => json_decode($price['price'], true),
                ];
            },
            $productPrices
        );

        return $productPrices;
    }

    private function findCheapestRulePrice(array $productPrices, string $ruleId): array
    {
        $cheapest = null;
        foreach ($productPrices as $price) {
            if ($price['ruleId'] !== $ruleId) {
                continue;
            }
            if ($cheapest === null) {
                $cheapest = $price;
                continue;
            }

            if ($price['price']['gross'] < $cheapest['price']['gross']) {
                $cheapest = $price;
            }
        }

        return $cheapest;
    }

    private function fetchProductPriceIds(array $priceIds): array
    {
        $priceIds = Uuid::fromHexToBytesList($priceIds);

        $query = $this->connection->createQueryBuilder();
        $query->select(['DISTINCT LOWER(HEX(IFNULL(product.parent_id, product.id)))']);
        $query->from('product_price');
        $query->innerJoin('product_price', 'product', 'product', 'product.id = product_price.product_id AND product.version_id = product_price.product_version_id');
        $query->where('product_price.id IN (:ids)');
        $query->setParameter(':ids', $priceIds, Connection::PARAM_STR_ARRAY);

        return $query->execute()->fetchAll(\PDO::FETCH_COLUMN);
    }
}
