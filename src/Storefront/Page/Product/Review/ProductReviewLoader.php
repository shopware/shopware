<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Product\Review;

use Shopware\Core\Content\Product\SalesChannel\Review\ProductReviewLoader as CoreProductReviewLoader;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Routing\Exception\MissingRequestParameterException;
use Shopware\Core\Framework\Routing\RoutingException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * @deprecated tag:v6.6.0 use Shopware\Core\Content\Product\SalesChannel\Review\ProductReviewLoader instead
 */
#[Package('storefront')]
class ProductReviewLoader
{
    /**
     * @internal
     */
    public function __construct(private readonly CoreProductReviewLoader $coreProductReviewLoader)
    {
    }

    /**
     * load reviews for one product. The request must contain the productId
     * otherwise MissingRequestParameterException is thrown
     *
     * @throws RoutingException
     * @throws InconsistentCriteriaIdsException
     */
    public function load(Request $request, SalesChannelContext $context): ReviewLoaderResult
    {
        Feature::triggerDeprecationOrThrow(
            'v6.6.0.0',
            '\Shopware\Storefront\Page\Product\Review\ProductReviewLoader will be removed use \Shopware\Core\Content\Product\SalesChannel\Review\ProductReviewLoader instead'
        );

        return ReviewLoaderResult::createFrom(
            $this->coreProductReviewLoader->load($request, $context)
        );
    }
}
