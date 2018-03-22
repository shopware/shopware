<?php

namespace Shopware\Storefront\Page\Listing\ListingHandler;

use Shopware\Api\Configuration\Collection\ConfigurationGroupOptionBasicCollection;
use Shopware\Api\Configuration\Definition\ConfigurationGroupOptionDefinition;
use Shopware\Api\Entity\Search\Aggregation\AggregationResult;
use Shopware\Api\Entity\Search\Aggregation\EntityAggregation;
use Shopware\Api\Entity\Search\AggregatorResult;
use Shopware\Api\Entity\Search\Criteria;
use Shopware\Api\Entity\Search\Query\NestedQuery;
use Shopware\Api\Entity\Search\Query\NotQuery;
use Shopware\Api\Entity\Search\Query\Query;
use Shopware\Api\Entity\Search\Query\TermsQuery;
use Shopware\Api\Entity\Search\SearchResultInterface;
use Shopware\Context\Struct\StorefrontContext;
use Shopware\Storefront\Page\Listing\AggregationView\ListAggregation;
use Shopware\Storefront\Page\Listing\AggregationView\ListItem;
use Shopware\Storefront\Page\Listing\ListingPageStruct;
use Symfony\Component\HttpFoundation\Request;

class DatasheetListingHandler implements ListingHandler
{
    public function prepareCriteria(Request $request, Criteria $criteria, StorefrontContext $context): void
    {
        $criteria->addAggregation(
            new EntityAggregation(
                'product.datasheet.id',
                ConfigurationGroupOptionDefinition::class,
                'datasheet'
            )
        );

        if (!$request->query->has('option')) {
            return;
        }

        $ids = $request->query->get('option', '');
        $ids= array_filter(explode('|', $ids));

        $criteria->addPostFilter(
            new TermsQuery('product.datasheet.id', $ids)
        );
    }

    public function preparePage(ListingPageStruct $listingPage, SearchResultInterface $searchResult, StorefrontContext $context): void
    {
        $result = $searchResult->getAggregationResult();

        if ($result === null) {
            return;
        }

        $aggregations = $result->getAggregations();

        /** @var AggregatorResult $result */
        if (!$aggregations->has('datasheet')) {
            return;
        }

        /** @var AggregationResult $aggregation */
        $aggregation = $aggregations->get('datasheet');

        $criteria = $searchResult->getCriteria();

        $filter = $this->getFilter($criteria->getPostFilters());

        $active = $filter !== null;

        $actives = $filter ? $filter->getValue() : [];

        /** @var ConfigurationGroupOptionBasicCollection $values */
        $values = $aggregation->getResult();

        if (!$values || $values->count() <= 0) {
            return;
        }

        $items = [];
        foreach ($values as $option) {
            $item = new ListItem(
                $option->getName(),
                \in_array($option->getId(), $actives, true),
                $option->getName()
            );

            $item->addExtension('option', $option);
            $items[] = $item;
        }

        $listingPage->getAggregations()->add(
            new ListAggregation('option', $active, 'Datasheet', 'option', $items)
        );
    }

    private function getFilter(NestedQuery $nested): ?TermsQuery
    {
        /** @var Query $query */
        foreach ($nested->getQueries() as $query) {
            if ($query instanceof TermsQuery && $query->getField() === 'product.datasheet.id') {
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