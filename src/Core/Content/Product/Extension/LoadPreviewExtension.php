<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Extension;

use Shopware\Core\Framework\Extensions\Extension;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
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
         */
        public readonly array $ids,
        /**
         * @public
         *
         * @description Allows you to access to the current customer/sales-channel context
         */
        public readonly SalesChannelContext $context
    ) {
    }
}
