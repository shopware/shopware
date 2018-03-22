<?php

namespace Shopware\Storefront\Page\Listing\ListingHandler;

use Shopware\Api\Entity\Search\Aggregation\AggregationResult;
use Shopware\Api\Entity\Search\Aggregation\EntityAggregation;
use Shopware\Api\Entity\Search\AggregatorResult;
use Shopware\Api\Entity\Search\Criteria;
use Shopware\Api\Entity\Search\Query\NestedQuery;
use Shopware\Api\Entity\Search\Query\NotQuery;
use Shopware\Api\Entity\Search\Query\Query;
use Shopware\Api\Entity\Search\Query\TermsQuery;
use Shopware\Api\Entity\Search\SearchResultInterface;
use Shopware\Api\Product\Collection\ProductManufacturerBasicCollection;
use Shopware\Api\Product\Definition\ProductManufacturerDefinition;
use Shopware\Api\Product\Repository\ProductManufacturerRepository;
use Shopware\Context\Struct\StorefrontContext;
use Shopware\Storefront\Page\Listing\AggregationView\ListAggregation;
use Shopware\Storefront\Page\Listing\AggregationView\ListItem;
use Shopware\Storefront\Page\Listing\ListingPageStruct;
use Symfony\Component\HttpFoundation\Request;

class ManufacturerListingHandler implements ListingHandler
{
    public const PRODUCT_MANUFACTURER_ID = 'product.manufacturerJoinId';

    /**
     * @var ProductManufacturerRepository
     */
    private $manufacturerRepository;

    public function __construct(ProductManufacturerRepository $manufacturerRepository)
    {
        $this->manufacturerRepository = $manufacturerRepository;
    }

    public function prepareCriteria(Request $request, Criteria $criteria, StorefrontContext $context): void
    {
        $criteria->addAggregation(
            new EntityAggregation(self::PRODUCT_MANUFACTURER_ID, ProductManufacturerDefinition::class, 'manufacturer')
        );

        if (!$request->query->has('manufacturer')) {
            return;
        }

        $names = $request->query->get('manufacturer', '');
        $names = array_filter(explode('|', $names));

        $search = new Criteria();
        $search->addFilter(new TermsQuery('product_manufacturer.name', $names));
        $ids = $this->manufacturerRepository->searchIds($search, $context->getShopContext());

        if (empty($ids->getIds())) {
            return;
        }

        $criteria->addPostFilter(new TermsQuery(self::PRODUCT_MANUFACTURER_ID, $ids->getIds()));
    }

    public function preparePage(ListingPageStruct $listingPage, SearchResultInterface $searchResult, StorefrontContext $context): void
    {
        $result = $searchResult->getAggregationResult();

        if ($result === null) {
            return;
        }

        $aggregations = $result->getAggregations();

        /** @var AggregatorResult $result */
        if (!$aggregations->has('manufacturer')) {
            return;
        }

        /** @var AggregationResult $aggregation */
        $aggregation = $aggregations->get('manufacturer');

        $criteria = $searchResult->getCriteria();

        $filter = $this->getFilter($criteria->getPostFilters());

        $active = $filter !== null;

        $actives = $filter ? $filter->getValue() : [];

        /** @var ProductManufacturerBasicCollection $values */
        $values = $aggregation->getResult();

        $items = [];
        foreach ($values as $manufacturer) {
            $item = new ListItem(
                $manufacturer->getName(),
                \in_array($manufacturer->getId(), $actives, true),
                $manufacturer->getName()
            );

            $item->addExtension('manufacturer', $manufacturer);
            $items[] = $item;
        }

        $listingPage->getAggregations()->add(
            new ListAggregation('manufacturer', $active, 'Manufacturer', 'manufacturer', $items)
        );
    }

    private function getFilter(NestedQuery $nested): ?TermsQuery
    {
        /** @var Query $query */
        foreach ($nested->getQueries() as $query) {
            if ($query instanceof TermsQuery && $query->getField() === self::PRODUCT_MANUFACTURER_ID) {
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