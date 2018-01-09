<?php declare(strict_types=1);

namespace Shopware\DbalIndexing\ListingPrice;

use Doctrine\DBAL\Connection;
use Monolog\Logger;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
use Shopware\Api\Customer\Repository\CustomerGroupRepository;
use Shopware\Api\Entity\Search\Criteria;
use Shopware\Api\Entity\Search\Query\TermsQuery;
use Shopware\Api\Entity\Write\GenericWrittenEvent;
use Shopware\Api\Product\Collection\ProductListingPriceBasicCollection;
use Shopware\Api\Product\Definition\ProductDefinition;
use Shopware\Api\Product\Definition\ProductPriceDefinition;
use Shopware\Api\Product\Repository\ProductPriceRepository;
use Shopware\Api\Product\Repository\ProductRepository;
use Shopware\Api\Product\Struct\ProductListingPriceBasicStruct;
use Shopware\Context\Struct\TranslationContext;
use Shopware\DbalIndexing\Common\IndexTableOperator;
use Shopware\DbalIndexing\Common\RepositoryIterator;
use Shopware\DbalIndexing\Event\ProgressAdvancedEvent;
use Shopware\DbalIndexing\Event\ProgressFinishedEvent;
use Shopware\DbalIndexing\Event\ProgressStartedEvent;
use Shopware\DbalIndexing\Indexer\IndexerInterface;
use Shopware\Framework\Doctrine\MultiInsertQueryQueue;
use Shopware\Framework\Event\NestedEventCollection;
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

        $customerGroups = $this->customerGroupRepository->searchIds(new Criteria(), $context);

        $this->indexTableOperator->createTable(self::TABLE, $timestamp);

        $iterator = new RepositoryIterator($this->productRepository, $context);

        $this->eventDispatcher->dispatch(
            ProgressStartedEvent::NAME,
            new ProgressStartedEvent('Start indexing listing prices', $iterator->getTotal())
        );

        while ($ids = $iterator->fetchIds()) {
            $this->indexListingPrices($ids, $customerGroups->getIds(), $timestamp);

            $this->eventDispatcher->dispatch(
                ProgressAdvancedEvent::NAME,
                new ProgressAdvancedEvent(count($ids))
            );
        }

        $this->connection->transactional(function () use ($timestamp) {
            $this->indexTableOperator->renameTable(self::TABLE, $timestamp);
            $this->connection->executeUpdate('ALTER TABLE product_listing_price ADD PRIMARY KEY (id)');
            $this->connection->executeUpdate('ALTER TABLE product_listing_price ADD CONSTRAINT FOREIGN KEY (product_id) REFERENCES product (id) ON DELETE CASCADE ON UPDATE CASCADE');
            $this->connection->executeUpdate('ALTER TABLE product_listing_price ADD CONSTRAINT FOREIGN KEY (customer_group_id) REFERENCES customer_group (id) ON DELETE CASCADE ON UPDATE CASCADE');
        });

        $this->eventDispatcher->dispatch(
            ProgressFinishedEvent::NAME,
            new ProgressFinishedEvent('Finished listing price indexing')
        );
    }

    public function refresh(GenericWrittenEvent $event): void
    {
        $ids = $this->getProductIds($event);
        if (empty($ids)) {
            return;
        }

        $customerGroups = $this->customerGroupRepository->searchIds(new Criteria(), $event->getContext());

        $this->connection->transactional(function () use ($ids, $customerGroups) {
            $this->connection->executeUpdate(
                'DELETE FROM product_listing_price WHERE product_id IN (:ids)',
                ['ids' => $ids],
                ['ids' => Connection::PARAM_STR_ARRAY]
            );

            $this->indexListingPrices($ids, $customerGroups->getIds(), null);
        });
    }

    private function indexListingPrices(
        array $ids,
        array $customerGroupIds,
        ?\DateTime $timestamp
    ): void {
        $listingPrices = $this->listingPriceLoader->load($ids);

        $table = $this->indexTableOperator->getIndexName(self::TABLE, $timestamp);

        $queue = new MultiInsertQueryQueue($this->connection);

        foreach ($ids as $productId) {
            $prices = $this->prepareProductPrices($productId, $listingPrices, $customerGroupIds);

            /** @var ProductListingPriceBasicStruct $price */
            foreach ($prices as $price) {
                $queue->addInsert(
                    $table,
                    [
                        'id' => Uuid::fromString($price->getId())->getBytes(),
                        'product_id' => Uuid::fromString($price->getProductId())->getBytes(),
                        'customer_group_id' => Uuid::fromString($price->getCustomerGroupId())->getBytes(),
                        'price' => $price->getPrice(),
                        'sorting_price' => $price->getSortingPrice(),
                        'display_from_price' => $price->getDisplayFromPrice() ? 1 : 0,
                    ],
                    [
                        'id' => \PDO::PARAM_STR,
                        'product_id' => \PDO::PARAM_STR,
                        'customer_group_id' => \PDO::PARAM_STR,
                        'display_from_price' => \PDO::PARAM_BOOL,
                    ]
                );
            }
        }
        $queue->execute();
    }

    private function prepareProductPrices(
        string $productId,
        ProductListingPriceBasicCollection $listingPrices,
        array $customerGroupIds
    ): ProductListingPriceBasicCollection {
        $prices = $listingPrices->filterByProductId($productId);

        /** @var ProductListingPriceBasicCollection $fallback */
        $fallback = $prices->filterByCustomerGroupId(StorefrontContextService::FALLBACK_CUSTOMER_GROUP);

        if ($fallback->count() <= 0) {
            $this->logger->log(Logger::WARNING, sprintf('Product %s has no default customer group price', $productId));

            return $prices;
        }

        $fallback = $fallback->first();

        /* @var ProductListingPriceBasicStruct $fallback */
        foreach ($customerGroupIds as $customerGroupId) {
            if ($customerGroupId === StorefrontContextService::FALLBACK_CUSTOMER_GROUP) {
                continue;
            }

            //check if customer prices exists
            $customerPrice = $prices->filterByCustomerGroupId($customerGroupId);
            if ($customerPrice->count() > 0) {
                continue;
            }

            $customerPrice = clone $fallback;
            $customerPrice->setCustomerGroupId($customerGroupId);
            $customerPrice->setId(Uuid::uuid4()->toString());
            $prices->add($customerPrice);
        }

        return $prices;
    }

    private function getProductIds(GenericWrittenEvent $event): array
    {
        /** @var NestedEventCollection $events */
        $products = $event->getEventByDefinition(ProductDefinition::class);

        $affectedPrices = $event->getEventByDefinition(ProductPriceDefinition::class);

        $ids = [];
        if ($products) {
            $ids = array_merge($ids, $products->getIds());
        }

        if ($affectedPrices) {
            $criteria = new Criteria();
            $criteria->addFilter(new TermsQuery('product_price.id', $affectedPrices->getIds()));
            $affectedPrices = $this->priceRepository->search($criteria, $event->getContext());
            $ids = array_merge($ids, $affectedPrices->getProductIds());
        }

        return array_unique($ids);
    }
}
