<?php

namespace Shopware\Storefront\Page\Listing\ListingHandler;

use Shopware\Api\Entity\Search\Criteria;
use Shopware\Api\Entity\Search\SearchResultInterface;
use Shopware\Context\Struct\StorefrontContext;
use Shopware\Storefront\Page\Listing\ListingPageStruct;
use Symfony\Component\HttpFoundation\Request;

interface ListingHandler
{
    public function prepareCriteria(Request $request, Criteria $criteria, StorefrontContext $context): void;

    public function preparePage(ListingPageStruct $listingPage, SearchResultInterface $searchResult, StorefrontContext $context): void;
}