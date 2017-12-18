<?php declare(strict_types=1);

namespace Shopware\DbalIndexing\ListingPrice;

use Doctrine\DBAL\Connection;
use Monolog\Logger;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
use Shopware\Api\Search\Criteria;
use Shopware\Api\Search\Query\TermsQuery;
use Shopware\Api\Write\GenericWrittenEvent;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Customer\Repository\CustomerGroupRepository;
use Shopware\DbalIndexing\Common\IndexTableOperator;
use Shopware\DbalIndexing\Common\RepositoryIterator;
use Shopware\DbalIndexing\Event\ProgressAdvancedEvent;
use Shopware\DbalIndexing\Event\ProgressFinishedEvent;
use Shopware\DbalIndexing\Event\ProgressStartedEvent;
use Shopware\DbalIndexing\Indexer\IndexerInterface;
use Shopware\Framework\Doctrine\MultiInsertQueryQueue;
use Shopware\Framework\Event\NestedEventCollection;
use Shopware\Product\Collection\ProductListingPriceBasicCollection;
use Shopware\Product\Definition\ProductDefinition;
use Shopware\Product\Definition\ProductPriceDefinition;
use Shopware\Product\Repository\ProductPriceRepository;
use Shopware\Product\Repository\ProductRepository;
use Shopware\Product\Struct\ProductListingPriceBasicStruct;
use Shopware\Storefront\Context\StorefrontContextService;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class ListingPriceIndexer implements IndexerInterface
{
    public const TABLE = 'product_listing_price';

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

    /**
     * @var IndexTableOperator
     */
    private $indexTableOperator;

    /**
     * @var ProductPriceRepository
     */
    private $priceRepository;

    public function __construct(
        ProductRepository $productRepository,
        CustomerGroupRepository $customerGroupRepository,
        ListingPriceLoader $listingPriceLoader,
        Connection $connection,
        EventDispatcherInterface $eventDispatcher,
        LoggerInterface $logger,
        IndexTableOperator $indexTableOperator,
        ProductPriceRepository $priceRepository
    ) {
        $this->productRepository = $productRepository;
        $this->listingPriceLoader = $listingPriceLoader;
        $this->connection = $connection;
        $this->eventDispatcher = $eventDispatcher;
        $this->logger = $logger;
        $this->customerGroupRepository = $customerGroupRepository;
        $this->indexTableOperator = $indexTableOperator;
        $this->priceRepository = $priceRepository;
    }

    public function index(\DateTime $timestamp): void
    {
        $context = TranslationContext::createDefaultContext();

        $customerGroups = $this->customerGroupRepository->searchUuids(new Criteria(), $context);

        $this->indexTableOperator->createTable(self::TABLE, $timestamp);

        $iterator = new RepositoryIterator($this->productRepository, $context);

        $this->eventDispatcher->dispatch(
            ProgressStartedEvent::NAME,
            new ProgressStartedEvent('Start indexing listing prices', $iterator->getTotal())
        );

        while ($uuids = $iterator->fetchUuids()) {
            $this->indexListingPrices($uuids, $customerGroups->getUuids(), $timestamp);

            $this->eventDispatcher->dispatch(
                ProgressAdvancedEvent::NAME,
                new ProgressAdvancedEvent(count($uuids))
            );
        }

        $this->connection->transactional(function () use ($timestamp) {
            $this->indexTableOperator->renameTable(self::TABLE, $timestamp);
            $this->connection->executeUpdate('ALTER TABLE product_listing_price ADD PRIMARY KEY (uuid)');
            $this->connection->executeUpdate('ALTER TABLE product_listing_price ADD CONSTRAINT FOREIGN KEY (product_uuid) REFERENCES product (uuid) ON DELETE CASCADE ON UPDATE CASCADE');
            $this->connection->executeUpdate('ALTER TABLE product_listing_price ADD CONSTRAINT FOREIGN KEY (customer_group_uuid) REFERENCES customer_group (uuid) ON DELETE CASCADE ON UPDATE CASCADE');
        });

        $this->eventDispatcher->dispatch(
            ProgressFinishedEvent::NAME,
            new ProgressFinishedEvent('Finished listing price indexing')
        );
    }

    public function refresh(GenericWrittenEvent $event): void
    {
        $uuids = $this->getProductUuids($event);
        if (empty($uuids)) {
            return;
        }

        $customerGroups = $this->customerGroupRepository->searchUuids(new Criteria(), $event->getContext());

        $this->connection->transactional(function () use ($uuids, $customerGroups) {
            $this->connection->executeUpdate(
                'DELETE FROM product_listing_price WHERE product_uuid IN (:uuids)',
                ['uuids' => $uuids],
                ['uuids' => Connection::PARAM_STR_ARRAY]
            );

            $this->indexListingPrices($uuids, $customerGroups->getUuids(), null);
        });
    }

    private function indexListingPrices(
        array $uuids,
        array $customerGroupUuids,
        ?\DateTime $timestamp
    ): void {
        $listingPrices = $this->listingPriceLoader->load($uuids);

        $table = $this->indexTableOperator->getIndexName(self::TABLE, $timestamp);

        $queue = new MultiInsertQueryQueue($this->connection);

        foreach ($uuids as $productUuid) {
            $prices = $this->prepareProductPrices($productUuid, $listingPrices, $customerGroupUuids);

            /** @var ProductListingPriceBasicStruct $price */
            foreach ($prices as $price) {
                $queue->addInsert(
                    $table,
                    [
                        'uuid' => $price->getUuid(),
                        'product_uuid' => $price->getProductUuid(),
                        'customer_group_uuid' => $price->getCustomerGroupUuid(),
                        'price' => $price->getPrice(),
                        'sorting_price' => $price->getSortingPrice(),
                        'display_from_price' => $price->getDisplayFromPrice() ? 1 : 0,
                    ],
                    [
                        'uuid' => \PDO::PARAM_STR,
                        'product_uuid' => \PDO::PARAM_STR,
                        'customer_group_uuid' => \PDO::PARAM_STR,
                        'display_from_price' => \PDO::PARAM_BOOL,
                    ]
                );
            }
        }
        $queue->execute();
    }

    private function prepareProductPrices(
        string $productUuid,
        ProductListingPriceBasicCollection $listingPrices,
        array $customerGroupUuids
    ): ProductListingPriceBasicCollection {
        $prices = $listingPrices->filterByProductUuid($productUuid);

        /** @var ProductListingPriceBasicCollection $fallback */
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

    private function getProductUuids(GenericWrittenEvent $event): array
    {
        /** @var NestedEventCollection $events */
        $products = $event->getEventByDefinition(ProductDefinition::class);

        $affectedPrices = $event->getEventByDefinition(ProductPriceDefinition::class);

        $uuids = [];
        if ($products) {
            $uuids = array_merge($uuids, $products->getUuids());
        }

        if ($affectedPrices) {
            $criteria = new Criteria();
            $criteria->addFilter(new TermsQuery('product_price.uuid', $affectedPrices->getUuids()));
            $affectedPrices = $this->priceRepository->search($criteria, $event->getContext());
            $uuids = array_merge($uuids, $affectedPrices->getProductUuids());
        }

        return array_unique($uuids);
    }
}
