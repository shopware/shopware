<?php declare(strict_types=1);

namespace Shopware\Core\System\SystemConfig\SalesChannel;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;

/**
 * Product listing, search and review settings (core.listing).
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
final class ShopListingSettings extends Struct
{
    public function __construct(
        public readonly int $productsPerPage,
        public readonly bool $allowBuyInListing,
        public readonly bool $showReview,
        public readonly int $reviewsPerPage,
        public readonly bool $disableEmptyFilterOptions,
        public readonly int $markAsNew,
        public readonly bool $hideCloseoutProductsWhenOutOfStock,
        public readonly bool $showVariantOptionInSearchSuggestionResult,
        public readonly bool $findBestVariant,
        public readonly bool $autoplayVideoInListing,
        public readonly string $beforeListPriceSnippetKey,
        public readonly string $afterListPriceSnippetKey,
    ) {
    }

    public function getApiAlias(): string
    {
        return 'shop_settings_listing';
    }
}
