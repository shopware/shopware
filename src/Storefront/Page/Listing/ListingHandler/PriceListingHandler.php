<?php

namespace Shopware\Storefront\Page\Listing\ListingHandler;

use Shopware\Api\Entity\Search\Aggregation\AggregationResult;
use Shopware\Api\Entity\Search\Aggregation\StatsAggregation;
use Shopware\Api\Entity\Search\AggregatorResult;
use Shopware\Api\Entity\Search\Criteria;
use Shopware\Api\Entity\Search\Query\NestedQuery;
use Shopware\Api\Entity\Search\Query\NotQuery;
use Shopware\Api\Entity\Search\Query\Query;
use Shopware\Api\Entity\Search\Query\RangeQuery;
use Shopware\Api\Entity\Search\SearchResultInterface;
use Shopware\Context\Struct\StorefrontContext;
use Shopware\Storefront\Page\Listing\AggregationView\SliderAggregation;
use Shopware\Storefront\Page\Listing\ListingPageStruct;
use Symfony\Component\HttpFoundation\Request;

class PriceListingHandler implements ListingHandler
{
    const PRICE_FIELD = 'product.listingPrices';

    public function prepareCriteria(Request $request, Criteria $criteria, StorefrontContext $context): void
    {
        $criteria->addAggregation(new StatsAggregation(self::PRICE_FIELD, 'price'));

        if (!$request->query->get('min-price') && !$request->query->has('max-price')) {
            return;
        }

        $range = [];
        if ($request->query->get('min-price')) {
            $range[RangeQuery::GTE] = (float) $request->query->get('min-price');
        }
        if ($request->query->get('max-price')) {
            $range[RangeQuery::LTE] = (float) $request->query->get('max-price');
        }

        $criteria->addPostFilter(new RangeQuery(self::PRICE_FIELD, $range));
    }

    public function preparePage(ListingPageStruct $listingPage, SearchResultInterface $searchResult, StorefrontContext $context): void
    {
        $result = $searchResult->getAggregationResult();

        if ($result === null) {
            return;
        }

        $aggregations = $result->getAggregations();

        /** @var AggregatorResult $result */
        if (!$aggregations->has('price')) {
            return;
        }

        /** @var AggregationResult $aggregation */
        $aggregation = $aggregations->get('price');

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

        $listingPage->getAggregations()->add(
            new SliderAggregation('price', $active, 'Price', (float) $values['min'], (float) $values['max'], $min, $max, 'min-price', 'max-price')
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