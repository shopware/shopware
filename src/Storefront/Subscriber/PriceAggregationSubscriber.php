<?php

namespace Shopware\Storefront\Subscriber;

use Shopware\Api\Entity\Search\Aggregation\AggregationResult;
use Shopware\Api\Entity\Search\Aggregation\StatsAggregation;
use Shopware\Api\Entity\Search\Query\NestedQuery;
use Shopware\Api\Entity\Search\Query\RangeQuery;
use Shopware\Storefront\Event\ListingEvents;
use Shopware\Storefront\Event\ListingPageLoadedEvent;
use Shopware\Storefront\Event\PageCriteriaCreatedEvent;
use Shopware\Storefront\Page\Listing\AggregationView\SliderAggregation;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class PriceAggregationSubscriber implements EventSubscriberInterface
{
    public const PRICE_FIELD = 'product.listingPrices';

    public const MIN_PRICE_PARAMETER = 'min-price';

    public const MAX_PRICE_PARAMETER = 'max-price';

    public const AGGREGATION_NAME = 'price';

    public static function getSubscribedEvents()
    {
        return [
            ListingEvents::PAGE_CRITERIA_CREATED_EVENT => 'buildCriteria',
            ListingEvents::LISTING_PAGE_LOADED_EVENT => 'buildAggregationView'
        ];
    }

    public function buildCriteria(PageCriteriaCreatedEvent $event): void
    {
        $event->getCriteria()->addAggregation(
            new StatsAggregation(self::PRICE_FIELD, self::AGGREGATION_NAME, false, false, false, true, true)
        );

        $request = $event->getRequest();

        if (!$request->query->get(self::MIN_PRICE_PARAMETER) && !$request->query->has(self::MAX_PRICE_PARAMETER)) {
            return;
        }

        $range = [];
        if ($request->query->get(self::MIN_PRICE_PARAMETER)) {
            $range[RangeQuery::GTE] = (float) $request->query->get(self::MIN_PRICE_PARAMETER);
        }
        if ($request->query->get(self::MAX_PRICE_PARAMETER)) {
            $range[RangeQuery::LTE] = (float) $request->query->get(self::MAX_PRICE_PARAMETER);
        }

        $query = new RangeQuery(self::PRICE_FIELD, $range);

        $event->getCriteria()->addPostFilter($query);
    }

    public function buildAggregationView(ListingPageLoadedEvent $event): void
    {
        $searchResult = $event->getPage()->getProducts();

        $result = $searchResult->getAggregationResult();

        if ($result === null) {
            return;
        }

        $aggregations = $result->getAggregations();

        /** @var AggregatorResult $result */
        if (!$aggregations->has(self::AGGREGATION_NAME)) {
            return;
        }

        /** @var AggregationResult $aggregation */
        $aggregation = $aggregations->get(self::AGGREGATION_NAME);

        $criteria = $searchResult->getCriteria();

        $filter = $this->getFilter($criteria->getPostFilters());

        $active = $filter !== null;

        $min = 0;
        $max = 0;
        if ($filter) {
            $min = (float) $filter->getParameter(RangeQuery::GTE);
            $max = (float) $filter->getParameter(RangeQuery::LTE);
        }

        $values = $aggregation->getResult();

        if ($values['min'] === (float) $values['max']) {
            return;
        }

        $event->getPage()->getAggregations()->add(
            new SliderAggregation(
                self::AGGREGATION_NAME,
                $active,
                'Price',
                (float) $values['min'],
                (float) $values['max'],
                $min,
                $max,
                self::MIN_PRICE_PARAMETER,
                self::MAX_PRICE_PARAMETER
            )
        );
    }

    private function getFilter(NestedQuery $nested): ?RangeQuery
    {
        /** @var Query $query */
        foreach ($nested->getQueries() as $query) {
            if ($query instanceof RangeQuery && $query->getField() === self::PRICE_FIELD) {
                return $query;
            }

            if (!$query instanceof NestedQuery || !$query instanceof NotQuery) {
                continue;
            }

            $found = $this->getFilter($query);

            if ($found) {
                return $found;
            }
        }

        return null;
    }
}