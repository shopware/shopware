<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Product\Review;

use Shopware\Core\Framework\Script\Execution\Awareness\SalesChannelContextAwareTrait;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Page\PageLoadedHook;

/**
 * Triggered when the ProductReviewsWidget is loaded
 *
 * @hook-use-case data_loading
 *
 * @since 6.4.8.0
 */
class ProductReviewsWidgetLoadedHook extends PageLoadedHook
{
    use SalesChannelContextAwareTrait;

    public const HOOK_NAME = 'product-reviews-loaded';

    private ReviewLoaderResult $reviews;

    public function __construct(ReviewLoaderResult $reviews, SalesChannelContext $context)
    {
        parent::__construct($context->getContext());
        $this->salesChannelContext = $context;
        $this->reviews = $reviews;
    }

    public function getName(): string
    {
        return self::HOOK_NAME;
    }

    public function getReviews(): ReviewLoaderResult
    {
        return $this->reviews;
    }
}
