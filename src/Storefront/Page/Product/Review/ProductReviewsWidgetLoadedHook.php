<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Product\Review;

use Shopware\Core\Content\Product\SalesChannel\Review\ProductReviewsWidgetLoadedHook as CoreProductReviewsWidgetLoadedHook;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Script\Execution\Awareness\SalesChannelContextAwareTrait;
use Shopware\Core\Framework\Script\Execution\DeprecatedHook;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Page\PageLoadedHook;

/**
 * Triggered when the ProductReviewsWidget is loaded
 *
 * @hook-use-case data_loading
 *
 * @deprecated tag:v6.7.0 - Use \Shopware\Core\Content\Product\SalesChannel\Review\ProductReviewsWidgetLoadedHook instead
 * @since 6.4.8.0
 *
 * @final
 */
#[Package('storefront')]
class ProductReviewsWidgetLoadedHook extends PageLoadedHook implements DeprecatedHook
{
    use SalesChannelContextAwareTrait;

    final public const HOOK_NAME = 'product-reviews-loaded';

    public function __construct(
        private readonly ReviewLoaderResult $reviews,
        SalesChannelContext $context
    ) {
        Feature::triggerDeprecationOrThrow('v6.7.0.0', Feature::deprecatedClassMessage(self::class, 'v6.7.0.0', CoreProductReviewsWidgetLoadedHook::class));

        parent::__construct($context->getContext());
        $this->salesChannelContext = $context;
    }

    public function getName(): string
    {
        Feature::triggerDeprecationOrThrow('v6.7.0.0', Feature::deprecatedClassMessage(self::class, 'v6.7.0.0', CoreProductReviewsWidgetLoadedHook::class));

        return self::HOOK_NAME;
    }

    public function getSalesChannelContext(): SalesChannelContext
    {
        Feature::triggerDeprecationOrThrow('v6.7.0.0', Feature::deprecatedClassMessage(self::class, 'v6.7.0.0', CoreProductReviewsWidgetLoadedHook::class));

        return $this->salesChannelContext;
    }

    public function getReviews(): ReviewLoaderResult
    {
        Feature::triggerDeprecationOrThrow('v6.7.0.0', Feature::deprecatedClassMessage(self::class, 'v6.7.0.0', CoreProductReviewsWidgetLoadedHook::class));

        return $this->reviews;
    }

    public static function getDeprecationNotice(): string
    {
        Feature::triggerDeprecationOrThrow('v6.7.0.0', Feature::deprecatedClassMessage(self::class, 'v6.7.0.0', CoreProductReviewsWidgetLoadedHook::class));

        return Feature::deprecatedClassMessage(self::class, 'v6.7.0.0', CoreProductReviewsWidgetLoadedHook::class);
    }
}
