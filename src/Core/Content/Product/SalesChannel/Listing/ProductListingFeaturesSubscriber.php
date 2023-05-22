<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\SalesChannel\Listing;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Shopware\Core\Content\Product\Events\ProductListingCollectFilterEvent;
use Shopware\Core\Content\Product\Events\ProductListingCriteriaEvent;
use Shopware\Core\Content\Product\Events\ProductListingResultEvent;
use Shopware\Core\Content\Product\Events\ProductSearchCriteriaEvent;
use Shopware\Core\Content\Product\Events\ProductSearchResultEvent;
use Shopware\Core\Content\Product\Events\ProductSuggestCriteriaEvent;
use Shopware\Core\Content\Product\SalesChannel\Exception\ProductSortingNotFoundException;
use Shopware\Core\Content\Product\SalesChannel\Sorting\ProductSortingCollection;
use Shopware\Core\Content\Product\SalesChannel\Sorting\ProductSortingEntity;
use Shopware\Core\Content\Property\Aggregate\PropertyGroupOption\PropertyGroupOptionCollection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\RepositoryIterator;
use Shopware\Core\Framework\DataAbstractionLayer\Doctrine\FetchModeHelper;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Aggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Bucket\FilterAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Bucket\TermsAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Metric\EntityAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Metric\MaxAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Metric\StatsAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Bucket\TermsResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Metric\EntityResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Profiling\Profiler;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[Package('inventory')]
class ProductListingFeaturesSubscriber implements EventSubscriberInterface
{
    final public const DEFAULT_SEARCH_SORT = 'score';

    final public const PROPERTY_GROUP_IDS_REQUEST_PARAM = 'property-whitelist';
    final public const ALREADY_HANDLED = 'already-handled';

    /**
     * @internal
     */
    public function __construct(
        private readonly ListingFeatures $listingFeatures
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            ProductListingCriteriaEvent::class => [
                ['handleListingRequest', 100],
                ['handleFlags', -100],
            ],
            ProductSuggestCriteriaEvent::class => [
                ['handleFlags', -100],
            ],
            ProductSearchCriteriaEvent::class => [
                ['handleSearchRequest', 100],
                ['handleFlags', -100],
            ],
            // todo Call new service inside this listeners
            // todo Call new service where event are dispatched
            // todo Implement new functions inside new service
            // todo add and consider "handled" flag where it got dispatched and listened
            ProductListingResultEvent::class => [
                ['handleResult', 100],
                ['removeScoreSorting', -100],
            ],
            ProductSearchResultEvent::class => 'handleResult',
        ];
    }

    public function handleFlags(ProductListingCriteriaEvent $event): void
    {
        $request = $event->getRequest();
        $criteria = $event->getCriteria();

        if ($criteria->hasState(self::ALREADY_HANDLED)) {
            return;
        }

       $this->listingFeatures->handleFlags($request, $criteria);
    }

    public function handleListingRequest(ProductListingCriteriaEvent $event): void
    {
        $request = $event->getRequest();
        $criteria = $event->getCriteria();
        $context = $event->getSalesChannelContext();

        if($criteria->hasState(self::ALREADY_HANDLED)){
            return;
        }

        $this->listingFeatures->handleListingRequest($request, $criteria, $context);
    }

    public function handleSearchRequest(ProductSearchCriteriaEvent $event): void
    {
        $request = $event->getRequest();
        $criteria = $event->getCriteria();
        $context = $event->getSalesChannelContext();

        if ($criteria->hasState(self::ALREADY_HANDLED)) {
            return;
        }

        $this->listingFeatures->handleSearchRequest($request, $criteria, $context);
    }

    // Fixme: move to ListingFeatures.php
    public function handleResult(ProductListingResultEvent $event): void
    {
        Profiler::trace('product-listing::feature-subscriber', function () use ($event): void {
            $this->groupOptionAggregations($event);

            $this->addCurrentFilters($event);

            $result = $event->getResult();

            /** @var ProductSortingCollection $sortings */
            $sortings = $result->getCriteria()->getExtension('sortings');
            $currentSortingKey = $this->getCurrentSorting($sortings, $event->getRequest())->getKey();

            $result->setSorting($currentSortingKey);

            $result->setAvailableSortings($sortings);

            $result->setPage($this->getPage($event->getRequest()));

            $result->setLimit($this->getLimit($event->getRequest(), $event->getSalesChannelContext()));
        });
    }

    // Fixme: Move to ListingFeatures.php
    public function removeScoreSorting(ProductListingResultEvent $event): void
    {
        $sortings = $event->getResult()->getAvailableSortings();

        $defaultSorting = $sortings->getByKey(self::DEFAULT_SEARCH_SORT);
        if ($defaultSorting !== null) {
            $sortings->remove($defaultSorting->getId());
        }

        $event->getResult()->setAvailableSortings($sortings);
    }
}
