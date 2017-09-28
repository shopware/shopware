<?php declare(strict_types=1);

namespace Shopware\DbalIndexing\Indexer;

use Doctrine\DBAL\Connection;
use Monolog\Logger;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
use Shopware\Context\Struct\TranslationContext;
use Shopware\CustomerGroup\Repository\CustomerGroupRepository;
use Shopware\DbalIndexing\Common\RepositoryIterator;
use Shopware\DbalIndexing\Event\ProgressAdvancedEvent;
use Shopware\DbalIndexing\Event\ProgressFinishedEvent;
use Shopware\DbalIndexing\Event\ProgressStartedEvent;
use Shopware\DbalIndexing\Loader\ListingPriceLoader;
use Shopware\Framework\Event\NestedEventCollection;
use Shopware\Product\Event\ProductWrittenEvent;
use Shopware\Product\Repository\ProductRepository;
use Shopware\ProductListingPrice\Struct\ProductListingPriceBasicCollection;
use Shopware\ProductListingPrice\Struct\ProductListingPriceBasicStruct;
use Shopware\Search\Criteria;
use Shopware\Storefront\Context\StorefrontContextService;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class ListingPriceIndexer implements IndexerInterface
{
    const TABLE = 'product_listing_price_ro';

    /**
     * @var ProductRepository
     */
    private $productRepository;

    /**
     * @var ListingPriceLoader
     */
    private $listingPriceLoader;

    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var CustomerGroupRepository
     */
    private $customerGroupRepository;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * @var LoggerInterface
     */
    private $logger;

    public function __construct(
        ProductRepository $productRepository,
        CustomerGroupRepository $customerGroupRepository,
        ListingPriceLoader $listingPriceLoader,
        Connection $connection,
        EventDispatcherInterface $eventDispatcher,
        LoggerInterface $logger
    ) {
        $this->productRepository = $productRepository;
        $this->listingPriceLoader = $listingPriceLoader;
        $this->connection = $connection;
        $this->eventDispatcher = $eventDispatcher;
        $this->logger = $logger;
        $this->customerGroupRepository = $customerGroupRepository;
    }

    public function index(TranslationContext $context, \DateTime $timestamp): void
    {
        if ($context->getShopUuid() !== 'SWAG-SHOP-UUID-1') {
            return;
        }

        $customerGroups = $this->customerGroupRepository->searchUuids(new Criteria(), $context);

        $this->createTable($timestamp);

        $iterator = new RepositoryIterator($this->productRepository, $context);

        $this->eventDispatcher->dispatch(
            ProgressStartedEvent::NAME,
            new ProgressStartedEvent('Start indexing listing prices', $iterator->getTotal())
        );

        while ($uuids = $iterator->fetchUuids()) {
            $this->indexListingPrices($uuids, $customerGroups->getUuids(), $context, $timestamp);

            $this->eventDispatcher->dispatch(
                ProgressAdvancedEvent::NAME,
                new ProgressAdvancedEvent(count($uuids))
            );
        }

        $this->renameTable($timestamp);
        $this->eventDispatcher->dispatch(
            ProgressFinishedEvent::NAME,
            new ProgressFinishedEvent('Finished listing price indexing')
        );
    }

    public function refresh(NestedEventCollection $events, TranslationContext $context): void
    {
        $uuids = $this->getProductUuids($events);
        if (empty($uuids)) {
            return;
        }

        $customerGroups = $this->customerGroupRepository->searchUuids(new Criteria(), $context);

        $this->connection->executeUpdate(
            'DELETE FROM product_listing_price_ro WHERE product_uuid IN (:uuids)',
            ['uuids' => $uuids],
            ['uuids' => Connection::PARAM_STR_ARRAY]
        );

        $this->indexListingPrices($uuids, $customerGroups->getUuids(), $context, null);
    }

    private function indexListingPrices(
        array $uuids,
        array $customerGroupUuids,
        TranslationContext $context,
        ?\DateTime $timestamp
    ): void {
        $listingPrices = $this->listingPriceLoader->load($uuids);

        $table = $this->getIndexName($timestamp);

        $insert = $this->connection->prepare('
            INSERT INTO ' . $table . ' (uuid, product_uuid, customer_group_uuid, price, display_from_price)
            VALUES (:uuid, :product_uuid, :customer_group_uuid, :price, :display_from_price)
        ');

        foreach ($uuids as $productUuid) {
            $prices = $this->prepareProductPrices($productUuid, $listingPrices, $customerGroupUuids);

            /** @var ProductListingPriceBasicStruct $price */
            foreach ($prices as $price) {
                $insert->execute([
                    'uuid' => $price->getUuid(),
                    'product_uuid' => $price->getProductUuid(),
                    'customer_group_uuid' => $price->getCustomerGroupUuid(),
                    'price' => $price->getPrice(),
                    'display_from_price' => $price->getDisplayFromPrice() ? 1 : 0
                ]);
            }
        }
    }

    private function prepareProductPrices(
        string $productUuid,
        ProductListingPriceBasicCollection $listingPrices,
        array $customerGroupUuids
    ): ProductListingPriceBasicCollection {
        $prices = $listingPrices->filterByProductUuid($productUuid);

        $fallback = $prices->filterByCustomerGroupUuid(StorefrontContextService::FALLBACK_CUSTOMER_GROUP);

        if ($fallback->count() <= 0) {
            $this->logger->log(Logger::WARNING, sprintf('Product %s has no default customer group price', $productUuid));

            return $prices;
        }

        $fallback = $fallback->first();

        /* @var ProductListingPriceBasicStruct $fallback */
        foreach ($customerGroupUuids as $customerGroupUuid) {
            if ($customerGroupUuid === StorefrontContextService::FALLBACK_CUSTOMER_GROUP) {
                continue;
            }

            //check if customer prices exists
            $customerPrice = $prices->filterByCustomerGroupUuid($customerGroupUuid);
            if ($customerPrice->count() > 0) {
                continue;
            }

            $customerPrice = clone $fallback;
            $customerPrice->setCustomerGroupUuid($customerGroupUuid);
            $customerPrice->setUuid(Uuid::uuid4()->toString());
            $prices->add($customerPrice);
        }

        return $prices;
    }

    private function getIndexName(?\DateTime $timestamp): string
    {
        if ($timestamp === null) {
            return self::TABLE;
        }

        return self::TABLE . '_' . $timestamp->format('YmdHis');
    }

    /**
     * @param NestedEventCollection $events
     *
     * @return array
     */
    private function getProductUuids(NestedEventCollection $events): array
    {
        /** @var NestedEventCollection $events */
        $events = $events
            ->getFlatEventList()
            ->filterInstance(ProductWrittenEvent::NAME);

        $uuids = [];
        /** @var ProductWrittenEvent $event */
        foreach ($events as $event) {
            foreach ($event->getProductUuids() as $uuid) {
                $uuids[] = $uuid;
            }
        }

        return $uuids;
    }

    private function renameTable(\DateTime $timestamp): void
    {
        $this->connection->transactional(function () use ($timestamp) {
            $name = $this->getIndexName($timestamp);
            $this->connection->executeUpdate('DROP TABLE ' . self::TABLE);
            $this->connection->executeUpdate('ALTER TABLE ' . $name . ' RENAME TO ' . self::TABLE);
        });
    }

    private function createTable(\DateTime $timestamp): void
    {
        $name = $this->getIndexName($timestamp);
        $this->connection->executeUpdate('
            DROP TABLE IF EXISTS ' . $name . ';
            CREATE TABLE ' . $name . ' SELECT * FROM ' . self::TABLE . ' LIMIT 0
        ');
    }
}
