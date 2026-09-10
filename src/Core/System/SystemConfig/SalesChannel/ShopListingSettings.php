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
    use ConfigCastTrait;

    /**
     * @internal
     */
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

    /**
     * @internal
     *
     * @param array<string, mixed> $config The values of the core.listing config domain
     */
    public static function fromConfig(array $config): self
    {
        return new self(
            productsPerPage: self::intValue($config, 'productsPerPage'),
            allowBuyInListing: self::boolValue($config, 'allowBuyInListing'),
            showReview: self::boolValue($config, 'showReview'),
            reviewsPerPage: self::intValue($config, 'reviewsPerPage'),
            disableEmptyFilterOptions: self::boolValue($config, 'disableEmptyFilterOptions'),
            markAsNew: self::intValue($config, 'markAsNew'),
            hideCloseoutProductsWhenOutOfStock: self::boolValue($config, 'hideCloseoutProductsWhenOutOfStock'),
            showVariantOptionInSearchSuggestionResult: self::boolValue($config, 'showVariantOptionInSearchSuggestionResult'),
            findBestVariant: self::boolValue($config, 'findBestVariant'),
            autoplayVideoInListing: self::boolValue($config, 'autoplayVideoInListing'),
            beforeListPriceSnippetKey: self::stringValue($config, 'beforeListPriceSnippetKey'),
            afterListPriceSnippetKey: self::stringValue($config, 'afterListPriceSnippetKey'),
        );
    }

    public function getApiAlias(): string
    {
        return 'shop_settings_listing';
    }
}
