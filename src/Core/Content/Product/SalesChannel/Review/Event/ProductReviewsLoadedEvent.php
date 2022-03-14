<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\SalesChannel\Review\Event;

use Shopware\Core\Content\Product\SalesChannel\Review\ProductReviewLoaderResult;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\NestedEvent;
use Shopware\Core\Framework\Event\ShopwareSalesChannelEvent;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

#[Package('inventory')]
class ProductReviewsLoadedEvent extends NestedEvent implements ShopwareSalesChannelEvent
{
    public function __construct(
        protected readonly ProductReviewLoaderResult $searchResult,
        protected readonly SalesChannelContext $salesChannelContext,
        protected readonly Request $request
    ) {
    }

    public function getSearchResult(): ProductReviewLoaderResult
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
