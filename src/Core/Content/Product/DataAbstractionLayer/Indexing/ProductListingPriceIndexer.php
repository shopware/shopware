<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\DataAbstractionLayer\Indexing;

use Doctrine\DBAL\Connection;
use Shopware\Core\Checkout\Cart\Price\PriceRounding;
use Shopware\Core\Content\Product\Aggregate\ProductPrice\ProductPriceDefinition;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Adapter\Cache\CacheClearer;
use Shopware\Core\Framework\DataAbstractionLayer\Cache\EntityCacheKeyGenerator;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\IteratorFactory;
use Shopware\Core\Framework\DataAbstractionLayer\Doctrine\FetchModeHelper;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityDeletedEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\IndexerInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\ListingPrice;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\ListingPriceCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\Price;
use Shopware\Core\Framework\Event\ProgressAdvancedEvent;
use Shopware\Core\Framework\Event\ProgressFinishedEvent;
use Shopware\Core\Framework\Event\ProgressStartedEvent;
use Shopware\Core\Framework\Uuid\Uuid;
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
     * @var CacheClearer
     */
    private $cache;

    /**
     * @var PriceRounding
     */
    private $priceRounding;

    /**
     * @var Price
     */
    private $priceStruct;

    /**
     * @var ListingPrice
     */
    private $listingPrice;

    public function __construct(
        EventDispatcherInterface $eventDispatcher,
        Connection $connection,
        IteratorFactory $iteratorFactory,
        ProductDefinition $productDefinition,
        EntityCacheKeyGenerator $cacheKeyGenerator,
        CacheClearer $cache,
        PriceRounding $priceRounding
    ) {
        $this->eventDispatcher = $eventDispatcher;
        $this->connection = $connection;
        $this->iteratorFactory = $iteratorFactory;
        $this->productDefinition = $productDefinition;
        $this->cacheKeyGenerator = $cacheKeyGenerator;
        $this->cache = $cache;
        $this->priceRounding = $priceRounding;
        $this->priceStruct = new Price('', 0, 0, true);
        $this->listingPrice = new ListingPrice();
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

    public function partial(?array $lastId, \DateTimeInterface $timestamp): ?array
    {
        $iterator = $this->iteratorFactory->createIterator($this->productDefinition, $lastId);

        $ids = $iterator->fetch();

        if (empty($ids)) {
            return null;
        }
        $this->update($ids);

        return $iterator->getOffset();
    }

    public function refresh(EntityWrittenContainerEvent $event): void
    {
        $nested = $event->getEventByEntityName(ProductPriceDefinition::ENTITY_NAME);

        if (!$nested) {
            return;
        }

        if (!$nested instanceof EntityDeletedEvent) {
            $ids = $this->fetchProductPriceIds($nested->getIds());
            $this->update(array_unique($ids));
        }

        $nested = $event->getEventByEntityName(ProductDefinition::ENTITY_NAME);
        if ($nested) {
            $this->update(array_unique($nested->getIds()));
        }
    }

    public static function getName(): string
    {
        return 'Swag.ProductListingPriceIndexer';
    }

    private function update(array $ids): void
    {
        if (empty($ids)) {
            return;
        }

        $currencies = $this->connection->fetchAll(
            'SELECT LOWER(HEX(id)) as array_key, LOWER(HEX(id)) as id, decimal_precision, factor 
             FROM currency'
        );
        $currencies = FetchModeHelper::groupUnique($currencies);

        $prices = $this->fetchPrices($ids, $currencies);

        $tags = [];

        $this->connection->executeUpdate(
            'UPDATE product SET listing_prices = NULL WHERE id IN (:ids)',
            ['ids' => Uuid::fromHexToBytesList($ids)],
            ['ids' => Connection::PARAM_STR_ARRAY]
        );

        foreach ($prices as $productId => $productPrices) {
            $ruleIds = array_keys(array_flip(array_column($productPrices, 'rule_id')));

            $listingPrices = [];

            foreach ($ruleIds as $ruleId) {
                foreach ($currencies as $currencyId => $_currency) {
                    $range = $this->calculatePriceRange($currencyId, $ruleId, $productPrices);

                    $currencyKey = 'c' . $currencyId;
                    $ruleKey = 'r' . $ruleId;

                    $listingPrices[$ruleKey][$currencyKey] = $range;
                }
            }

            $structs = $this->hydrate($listingPrices);

            $encoded = json_encode(['structs' => serialize($structs), 'formatted' => $listingPrices]);

            $this->connection->executeUpdate(
                'UPDATE product SET listing_prices = :price WHERE id = :id',
                [
                    'price' => $encoded,
                    'id' => Uuid::fromHexToBytes($productId),
                ]
            );

            $tags[] = $this->cacheKeyGenerator->getEntityTag($productId, $this->productDefinition);
        }

        $this->cache->invalidateTags($tags);
    }

    private function fetchPrices(array $ids, array $currencies): array
    {
        $query = $this->connection->createQueryBuilder();
        $query->select([
            'LOWER(HEX(IFNULL(product.parent_id, product.id))) as id',
            'LOWER(HEX(price.id)) as price_id',
            'LOWER(HEX(product.id)) as variant_id',
            'LOWER(HEX(price.rule_id)) as rule_id',
            'price.price',
        ]);

        $query->from('product', 'product');
        $query->innerJoin('product', 'product_price', 'price', 'price.product_id = product.id');
        $query->andWhere('product.id IN (:ids) OR product.parent_id IN (:ids)');

        $ids = Uuid::fromHexToBytesList($ids);

        $query->setParameter('ids', $ids, Connection::PARAM_STR_ARRAY);

        $data = $query->execute()->fetchAll();

        foreach ($data as &$row) {
            $price = json_decode($row['price'], true);

            $key = 'c' . Defaults::CURRENCY;

            if (!isset($price[$key])) {
                throw new \RuntimeException(sprintf('Missing default price for product %s', $row['variant_id']));
            }

            $default = $price[$key];

            foreach ($currencies as $currencyId => $currency) {
                $key = 'c' . $currencyId;

                if (isset($price[$key])) {
                    continue;
                }

                $currencyPrice = $default;

                $currencyPrice['gross'] = $this->priceRounding->round(
                    $currencyPrice['gross'] * $currency['factor'],
                    (int) $currency['decimal_precision']
                );

                $currencyPrice['net'] = $this->priceRounding->round(
                    $currencyPrice['net'] * $currency['factor'],
                    (int) $currency['decimal_precision']
                );

                $currencyPrice['currencyId'] = $currencyId;

                $price[$key] = $currencyPrice;
            }

            $row['price'] = $price;
        }

        return FetchModeHelper::group($data);
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

    private function calculatePriceRange(string $currencyId, string $ruleId, array $prices): array
    {
        $highest = null;
        $cheapest = null;

        foreach ($prices as $price) {
            if ($price['rule_id'] !== $ruleId) {
                continue;
            }

            $currencyPrice = $price['price']['c' . $currencyId];

            if (!$highest || $currencyPrice['gross'] > $highest['gross']) {
                $highest = $currencyPrice;
            }
            if (!$cheapest || $currencyPrice['gross'] < $cheapest['gross']) {
                $cheapest = $currencyPrice;
            }
        }

        return [
            'currencyId' => $currencyId,
            'ruleId' => $ruleId,
            'from' => $cheapest,
            'to' => $highest,
        ];
    }

    private function hydrate(array $listingPrices): ListingPriceCollection
    {
        $prices = new ListingPriceCollection();

        foreach ($listingPrices as $rulePrices) {
            foreach ($rulePrices as $price) {
                $to = clone $this->priceStruct;
                $from = clone $this->priceStruct;

                $to->assign($price['to']);
                $from->assign($price['from']);

                $price['to'] = $to;
                $price['from'] = $from;

                $listingPrice = clone $this->listingPrice;
                $listingPrice->assign($price);

                $prices->add($listingPrice);
            }
        }

        return $prices;
    }
}
