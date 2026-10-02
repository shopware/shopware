<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Extension;

use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\Filter;
use Shopware\Core\Framework\Extensions\Extension;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * @public this class is used as type-hint for all event listeners, so the class string is "public consumable" API
 *
 * @title Determination of the preview loading in product listing
 *
 * @description This event allows intercepting the loading preview of product listing when the product IDs should be determined
 *
 * @codeCoverageIgnore
 *
 * @extends Extension<array>
 */
#[Package('inventory')]
final class LoadPreviewExtension extends Extension
{
    public const NAME = 'listing-loader.load-previews';

    /**
     * @internal shopware owns the __constructor, but the properties are public API
     */
    public function __construct(
        /**
         * @public
         *
         * @description The array should contain a list of product ids.
         *
         * @var array<string>
         */
        public readonly array $ids,
        /**
         * @public
         *
         * @description Allows you to access to the current customer/sales-channel context
         */
        public readonly SalesChannelContext $context,
        /**
         * @public
         *
         * @description The active post filters of the listing. A configured main variant is only used as preview if it matches them.
         *
         * @var list<Filter>
         */
        public readonly array $postFilters = [],
        /**
         * @public
         *
         * @description The filters of the listing, for example the conditions of a dynamic product group. A configured main variant is only used as preview if it matches them as well. Empty for search results, which only check the post filters.
         *
         * @var list<Filter>
         */
        public readonly array $filters = []
    ) {
    }
}
