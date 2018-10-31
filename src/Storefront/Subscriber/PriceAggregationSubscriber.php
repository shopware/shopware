<?php declare(strict_types=1);

namespace Shopware\Storefront\Subscriber;

use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\StatsAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\StatsAggregationResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Query\NestedQuery;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Query\Query;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Query\RangeFilter;
use Shopware\Storefront\Event\ListingEvents;
use Shopware\Storefront\Event\ListingPageLoadedEvent;
use Shopware\Storefront\Event\ListingPageRequestEvent;
use Shopware\Storefront\Event\PageCriteriaCreatedEvent;
use Shopware\Storefront\Page\Listing\AggregationView\SliderAggregation;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class PriceAggregationSubscriber implements EventSubscriberInterface
{
    public const PRICE_FIELD = 'product.listingPrices';

    public const MIN_PRICE_PARAMETER = 'min-price';

    public const MAX_PRICE_PARAMETER = 'max-price';

    public const AGGREGATION_NAME = 'price';

    public static function getSubscribedEvents()
    {
        return [
            ListingEvents::CRITERIA_CREATED => 'buildCriteria',
            ListingEvents::LOADED => 'buildPage',
            ListingEvents::REQUEST => 'transformRequest',
        ];
    }

    public function transformRequest(ListingPageRequestEvent $event): void
    {
        $request = $event->getRequest();
        if (!$request->query->get(self::MIN_PRICE_PARAMETER) && !$request->query->has(self::MAX_PRICE_PARAMETER)) {
            return;
        }

        if ($request->query->get(self::MIN_PRICE_PARAMETER)) {
            $event->getListingPageRequest()->setMinPrice(
                (float) $request->query->get(self::MIN_PRICE_PARAMETER)
            );
        }

        if ($request->query->get(self::MAX_PRICE_PARAMETER)) {
            $event->getListingPageRequest()->setMaxPrice(
                (float) $request->query->get(self::MAX_PRICE_PARAMETER)
            );
        }
    }

    public function buildCriteria(PageCriteriaCreatedEvent $event): void
    {
        $event->getCriteria()->addAggregation(
            new StatsAggregation(self::PRICE_FIELD, self::AGGREGATION_NAME, false, false, false, true, true)
        );

        $request = $event->getRequest();

        $range = [];
        if ($request->getMinPrice() !== null) {
            $range[RangeFilter::GTE] = $request->getMinPrice();
        }
        if ($request->getMaxPrice() !== null) {
            $range[RangeFilter::LTE] = $request->getMaxPrice();
        }

        if (empty($range)) {
            return;
        }
        $query = new RangeFilter(self::PRICE_FIELD, $range);

        $event->getCriteria()->addPostFilter($query);
    }

    public function buildPage(ListingPageLoadedEvent $event): void
    {
        $searchResult = $event->getPage()->getProducts();

        $result = $searchResult->getAggregations();

        if ($result->count() <= 0) {
            return;
        }

        if (!$result->has(self::AGGREGATION_NAME)) {
            return;
        }

        /** @var StatsAggregationResult $aggregation */
        $aggregation = $result->get(self::AGGREGATION_NAME);

        $criteria = $searchResult->getCriteria();

        $filter = $this->getFilter(...$criteria->getPostFilters());

        $active = $filter !== null;

        $min = 0;
        $max = 0;
        if ($filter) {
            $min = (float) $filter->getParameter(RangeFilter::GTE);
            $max = (float) $filter->getParameter(RangeFilter::LTE);
        }

        if ($aggregation->getMin() === $aggregation->getMax()) {
            return;
        }

        $event->getPage()->getAggregations()->add(
            new SliderAggregation(
                self::AGGREGATION_NAME,
                $active,
                'Price',
                $aggregation->getMin(),
                $aggregation->getMax(),
                $min,
                $max,
                self::MIN_PRICE_PARAMETER,
                self::MAX_PRICE_PARAMETER
            )
        );
    }

    private function getFilter(Query ...$nested): ?RangeFilter
    {
        foreach ($nested as $query) {
            if ($query instanceof RangeFilter && $query->getField() === self::PRICE_FIELD) {
                return $query;
            }

            if (!$query instanceof NestedQuery) {
                continue;
            }

            $found = $this->getFilter(...$query->getQueries());
            if ($found) {
                return $found;
            }
        }

        return null;
    }
}
