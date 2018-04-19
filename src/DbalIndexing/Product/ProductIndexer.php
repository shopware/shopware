<?php declare(strict_types=1);

namespace Shopware\DbalIndexing\Product;

use Doctrine\DBAL\Connection;
use Shopware\Api\Entity\Write\GenericWrittenEvent;
use Shopware\Api\Product\Definition\ProductCategoryDefinition;
use Shopware\Api\Product\Definition\ProductDefinition;
use Shopware\Api\Product\Repository\ProductRepository;
use Shopware\Category\Extension\CategoryPathBuilder;
use Shopware\Context\Struct\ApplicationContext;
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

    /**
     * @var EventIdExtractor
     */
    private $eventIdExtractor;

    public function __construct(
        ProductRepository $productRepository,
        Connection $connection,
        EventDispatcherInterface $eventDispatcher,
        CategoryPathBuilder $pathBuilder,
        CategoryAssignmentUpdater $categoryAssignmentUpdater,
        InheritanceJoinIdUpdater $inheritanceJoinIdUpdater,
        ListingPriceUpdater $listingPriceUpdater,
        VariationJsonUpdater $variationJsonUpdater,
        DatasheetJsonUpdater $datasheetJsonUpdater,
        EventIdExtractor $eventIdExtractor
    ) {
        $this->productRepository = $productRepository;
        $this->connection = $connection;
        $this->eventDispatcher = $eventDispatcher;
        $this->pathBuilder = $pathBuilder;
        $this->categoryAssignmentUpdater = $categoryAssignmentUpdater;
        $this->inheritanceJoinIdUpdater = $inheritanceJoinIdUpdater;
        $this->listingPriceUpdater = $listingPriceUpdater;
        $this->variationJsonUpdater = $variationJsonUpdater;
        $this->datasheetJsonUpdater = $datasheetJsonUpdater;
        $this->eventIdExtractor = $eventIdExtractor;
    }

    public function index(\DateTime $timestamp, string $tenantId): void
    {
        $context = ApplicationContext::createDefaultContext($tenantId);

        $this->pathBuilder->update(Defaults::ROOT_CATEGORY, $context);

        $iterator = new RepositoryIterator($this->productRepository, $context);

        $this->eventDispatcher->dispatch(
            ProgressStartedEvent::NAME,
            new ProgressStartedEvent('Start indexing products (category assignment, inheritance, listing prices)', $iterator->getTotal())
        );

        while ($ids = $iterator->fetchIds()) {
            $this->inheritanceJoinIdUpdater->update($ids, $context);

            $this->categoryAssignmentUpdater->update($ids, $context);

            $this->listingPriceUpdater->update($ids, $context);

            $this->variationJsonUpdater->update($ids, $context);

            $this->datasheetJsonUpdater->update($ids, $context);

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
            $ids = $this->eventIdExtractor->getProductIds($event);

            $this->categoryAssignmentUpdater->update($ids, $event->getContext());

            $this->listingPriceUpdater->update($ids, $event->getContext());

            $this->variationJsonUpdater->update($ids, $event->getContext());

            $this->datasheetJsonUpdater->update($ids, $event->getContext());
        });
    }
}
