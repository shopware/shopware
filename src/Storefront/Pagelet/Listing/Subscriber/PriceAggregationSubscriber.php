<?php declare(strict_types=1);

namespace Shopware\Storefront\Pagelet\Listing\Subscriber;

use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\StatsAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\StatsAggregationResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\Filter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;
use Shopware\Storefront\Event\ListingEvents;
use Shopware\Storefront\Framework\Page\AggregationView\SliderAggregation;
use Shopware\Storefront\Pagelet\Listing\ListingPageletLoadedEvent;
use Shopware\Storefront\Pagelet\Listing\PageCriteriaCreatedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class PriceAggregationSubscriber implements EventSubscriberInterface
{
    public const PRICE_FIELD = 'product.listingPrices';

    public const MIN_PRICE_PARAMETER = 'min-price';

    public const MAX_PRICE_PARAMETER = 'max-price';

    public const AGGREGATION_NAME = 'price';

    public static function getSubscribedEvents(): array
    {
        return [
            ListingEvents::CRITERIA_CREATED => 'buildCriteria',
            ListingEvents::LISTING_PAGELET_LOADED => 'buildPage',
        ];
    }

    public function buildCriteria(PageCriteriaCreatedEvent $event): void
    {
        $event->getCriteria()->addAggregation(
            new StatsAggregation(self::PRICE_FIELD, self::AGGREGATION_NAME, false, false, false, true, true)
        );

        $request = $event->getRequest();

        $min = $request->optionalGet(self::MIN_PRICE_PARAMETER);
        $max = $request->optionalGet(self::MAX_PRICE_PARAMETER);

        $range = [];
        if ($min !== null) {
            $range[RangeFilter::GTE] = (float) $min;
        }
        if ($max !== null) {
            $range[RangeFilter::LTE] = (float) $max;
        }

        if (empty($range)) {
            return;
        }
        $query = new RangeFilter(self::PRICE_FIELD, $range);

        $event->getCriteria()->addPostFilter($query);
    }

    public function buildPage(ListingPageletLoadedEvent $event): void
    {
        $searchResult = $event->getPage()->getProducts();
        if (!$searchResult) {
            return;
        }
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

    private function getFilter(Filter ...$nested): ?RangeFilter
    {
        foreach ($nested as $query) {
            if ($query instanceof RangeFilter && $query->getField() === self::PRICE_FIELD) {
                return $query;
            }

            if (!$query instanceof MultiFilter) {
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
