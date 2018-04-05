<?php declare(strict_types=1);

namespace Shopware\DbalIndexing\Product;

use Doctrine\DBAL\Connection;
use Shopware\Api\Entity\Search\Criteria;
use Shopware\Api\Entity\Search\Query\TermQuery;
use Shopware\Api\Entity\Write\GenericWrittenEvent;
use Shopware\Api\Product\Definition\ProductCategoryDefinition;
use Shopware\Api\Product\Definition\ProductDefinition;
use Shopware\Api\Product\Repository\ProductRepository;
use Shopware\Api\Shop\Repository\ShopRepository;
use Shopware\Api\Shop\Struct\ShopBasicStruct;
use Shopware\Category\Extension\CategoryPathBuilder;
use Shopware\Context\Struct\ShopContext;
use Shopware\DbalIndexing\Common\RepositoryIterator;
use Shopware\DbalIndexing\Event\ProgressAdvancedEvent;
use Shopware\DbalIndexing\Event\ProgressFinishedEvent;
use Shopware\DbalIndexing\Event\ProgressStartedEvent;
use Shopware\DbalIndexing\Indexer\IndexerInterface;
use Shopware\Defaults;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class ProductIndexer implements IndexerInterface
{
    /**
     * @var ProductRepository
     */
    private $productRepository;

    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * @var CategoryPathBuilder
     */
    private $pathBuilder;

    /**
     * @var ShopRepository
     */
    private $shopRepository;

    /**
     * @var CategoryAssignmentUpdater
     */
    private $categoryAssignmentUpdater;

    /**
     * @var InheritanceJoinIdUpdater
     */
    private $inheritanceJoinIdUpdater;

    /**
     * @var ListingPriceUpdater
     */
    private $listingPriceUpdater;

    /**
     * @var VariationJsonUpdater
     */
    private $variationJsonUpdater;

    /**
     * @var DatasheetJsonUpdater
     */
    private $datasheetJsonUpdater;

    public function __construct(
        ProductRepository $productRepository,
        Connection $connection,
        EventDispatcherInterface $eventDispatcher,
        CategoryPathBuilder $pathBuilder,
        ShopRepository $shopRepository,
        CategoryAssignmentUpdater $categoryAssignmentUpdater,
        InheritanceJoinIdUpdater $inheritanceJoinIdUpdater,
        ListingPriceUpdater $listingPriceUpdater,
        VariationJsonUpdater $variationJsonUpdater,
        DatasheetJsonUpdater $datasheetJsonUpdater

    ) {
        $this->productRepository = $productRepository;
        $this->connection = $connection;
        $this->eventDispatcher = $eventDispatcher;
        $this->pathBuilder = $pathBuilder;
        $this->shopRepository = $shopRepository;
        $this->categoryAssignmentUpdater = $categoryAssignmentUpdater;
        $this->inheritanceJoinIdUpdater = $inheritanceJoinIdUpdater;
        $this->listingPriceUpdater = $listingPriceUpdater;
        $this->variationJsonUpdater = $variationJsonUpdater;
        $this->datasheetJsonUpdater = $datasheetJsonUpdater;
    }

    public function index(\DateTime $timestamp): void
    {
        $shop = $this->getDefaultShop();

        $context = ShopContext::createFromShop($shop);

        $this->pathBuilder->update(Defaults::ROOT_CATEGORY, $context);

        $iterator = new RepositoryIterator($this->productRepository, $context);

        $this->eventDispatcher->dispatch(
            ProgressStartedEvent::NAME,
            new ProgressStartedEvent('Start indexing products (category assignment, inheritance, listing prices)', $iterator->getTotal())
        );

        while ($ids = $iterator->fetchIds()) {
            $this->inheritanceJoinIdUpdater->update($ids, $context);

            $this->categoryAssignmentUpdater->update($ids, $context);

            $this->listingPriceUpdater->update($ids);

            $this->variationJsonUpdater->update($ids);

            $this->datasheetJsonUpdater->update($ids);

            $this->eventDispatcher->dispatch(
                ProgressAdvancedEvent::NAME,
                new ProgressAdvancedEvent(count($ids))
            );
        }

        $this->eventDispatcher->dispatch(
            ProgressFinishedEvent::NAME,
            new ProgressFinishedEvent('Finished indexing products')
        );
    }

    public function refresh(GenericWrittenEvent $event): void
    {
        $this->inheritanceJoinIdUpdater->updateByEvent($event);

        $this->connection->transactional(function () use ($event) {
            $ids = $this->getRefreshedProductIds($event);

            $this->categoryAssignmentUpdater->update($ids, $event->getContext());

            $this->listingPriceUpdater->update($ids);

            $this->variationJsonUpdater->update($ids);

            $this->datasheetJsonUpdater->update($ids);
        });
    }

    private function getRefreshedProductIds(GenericWrittenEvent $generic): array
    {
        $ids = [];

        $event = $generic->getEventByDefinition(ProductCategoryDefinition::class);
        if ($event) {
            foreach ($event->getIds() as $id) {
                $ids[] = $id['productId'];
            }
        }

        $event = $generic->getEventByDefinition(ProductDefinition::class);
        if ($event) {
            foreach ($event->getIds() as $id) {
                $ids[] = $id;
            }
        }

        return $ids;
    }

    private function getDefaultShop(): ShopBasicStruct
    {
        $criteria = new Criteria();
        $criteria->addFilter(new TermQuery('shop.isDefault', true));
        $result = $this->shopRepository->search($criteria, ShopContext::createDefaultContext());

        return $result->first();
    }
}
