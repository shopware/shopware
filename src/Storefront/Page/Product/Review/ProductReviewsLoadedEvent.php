<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Product\Review;

use Shopware\Core\Content\Product\Aggregate\ProductReview\ProductReviewCollection;
use Shopware\Core\Content\Product\SalesChannel\Review\Event\ProductReviewsLoadedEvent as CoreProductReviewsLoadedEvent;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\NestedEvent;
use Shopware\Core\Framework\Event\ShopwareSalesChannelEvent;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Framework\Page\StorefrontSearchResult;
use Symfony\Component\HttpFoundation\Request;

/**
 * @deprecated tag:v6.7.0 - Use \Shopware\Core\Content\Product\SalesChannel\Review\Event\ProductReviewsLoadedEvent instead
 */
#[Package('storefront')]
class ProductReviewsLoadedEvent extends NestedEvent implements ShopwareSalesChannelEvent
{
    /**
     * @var StorefrontSearchResult<ProductReviewCollection>
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

    /**
     * @param StorefrontSearchResult<ProductReviewCollection> $searchResult
     */
    public function __construct(
        StorefrontSearchResult $searchResult,
        SalesChannelContext $salesChannelContext,
        Request $request
    ) {
        $this->searchResult = $searchResult;
        $this->salesChannelContext = $salesChannelContext;
        $this->request = $request;
    }

    /**
     * @return StorefrontSearchResult<ProductReviewCollection>
     */
    public function getSearchResult(): StorefrontSearchResult
    {
        Feature::triggerDeprecationOrThrow('v6.7.0.0', Feature::deprecatedClassMessage(self::class, 'v6.7.0.0', CoreProductReviewsLoadedEvent::class));

        return $this->searchResult;
    }

    public function getSalesChannelContext(): SalesChannelContext
    {
        Feature::triggerDeprecationOrThrow('v6.7.0.0', Feature::deprecatedClassMessage(self::class, 'v6.7.0.0', CoreProductReviewsLoadedEvent::class));

        return $this->salesChannelContext;
    }

    public function getContext(): Context
    {
        Feature::triggerDeprecationOrThrow('v6.7.0.0', Feature::deprecatedClassMessage(self::class, 'v6.7.0.0', CoreProductReviewsLoadedEvent::class));

        return $this->salesChannelContext->getContext();
    }

    public function getRequest(): Request
    {
        Feature::triggerDeprecationOrThrow('v6.7.0.0', Feature::deprecatedClassMessage(self::class, 'v6.7.0.0', CoreProductReviewsLoadedEvent::class));

        return $this->request;
    }
}
