<?php

namespace Shopware\Storefront\Page\Listing\ListingHandler;

use Shopware\Api\Entity\Search\Criteria;
use Shopware\Api\Entity\Search\SearchResultInterface;
use Shopware\Context\Struct\StorefrontContext;
use Shopware\Storefront\Page\Listing\ListingPageStruct;
use Symfony\Component\HttpFoundation\Request;

class ListingHandlerRegistry implements ListingHandler
{
    /**
     * @var ListingHandler[]
     */
    protected $handlers;

    /**
     * @param ListingHandler[]|iterable $handlers
     */
    public function __construct(iterable $handlers)
    {
        $this->handlers = $handlers;
    }

    public function prepareCriteria(Request $request, Criteria $criteria, StorefrontContext $context): void
    {
        foreach ($this->handlers as $handler) {
            $handler->prepareCriteria($request, $criteria, $context);
        }
    }

    public function preparePage(ListingPageStruct $listingPage, SearchResultInterface $searchResult, StorefrontContext $context): void
    {
        foreach ($this->handlers as $handler) {
            $handler->preparePage($listingPage, $searchResult, $context);
        }
    }
}