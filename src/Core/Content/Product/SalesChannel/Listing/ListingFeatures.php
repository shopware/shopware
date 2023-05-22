<?php

namespace Shopware\Core\Content\Product\SalesChannel\Listing;

use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

class ListingFeatures
{
    final public const DEFAULT_SEARCH_SORT = 'score';

    // todo implement abstract class
    public function getDecorated(): self
    {
        throw new DecorationPatternException(self::class);
    }

    public function __construct(
        private readonly ProductListingFeaturesSubscriber $productListingFeaturesSubscriber
    )
    {
    }

    public function handleFlags(Request $request, Criteria $criteria): void
    {
        if ($request->get('no-aggregations')) {
            $criteria->resetAggregations();
        }

        if ($request->get('only-aggregations')) {
            // set limit to zero to fetch no products.
            $criteria->setLimit(0);

            // no total count required
            $criteria->setTotalCountMode(Criteria::TOTAL_COUNT_MODE_NONE);

            // sorting and association are only required for the product data
            $criteria->resetSorting();
            $criteria->resetAssociations();
        }
    }

    public function handleSearchRequest(Request $request, Criteria $criteria, SalesChannelContext $context): void
    {
        if (!$request->get('order')) {
            $request->request->set('order', self::DEFAULT_SEARCH_SORT);
        }

        $this->productListingFeaturesSubscriber->handlePagination($request, $criteria, $context);

        $this->productListingFeaturesSubscriber->handleFilters($request, $criteria, $context);

        $this->productListingFeaturesSubscriber->handleSorting($request, $criteria, $context);
    }
}
