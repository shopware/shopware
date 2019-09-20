<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Product\Review;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\NestedEvent;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Framework\Page\StorefrontSearchResult;
use Symfony\Component\HttpFoundation\Request;

class ProductReviewsLoadedEvent extends NestedEvent
{
    /**
     * @var StorefrontSearchResult
     */
    protected $searchResult;

    /**
     * @var SalesChannelContext
     */
    protected $salesChannelContext;

    /**
     * @var Request
     */
    protected $request;

    public function __construct(StorefrontSearchResult $searchResult, SalesChannelContext $salesChannelContext, Request $request)
    {
        $this->searchResult = $searchResult;
        $this->salesChannelContext = $salesChannelContext;
        $this->request = $request;
    }

    public function getSearchResult(): StorefrontSearchResult
    {
        return $this->searchResult;
    }

    public function getSalesChannelContext(): SalesChannelContext
    {
        return $this->salesChannelContext;
    }

    public function getContext(): Context
    {
        return $this->salesChannelContext->getContext();
    }

    public function getRequest(): Request
    {
        return $this->request;
    }
}
