<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Extension;

use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\Extensions\Extension;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * @public this class is used as type-hint for all event listeners, so the class string is "public consumable" API
 *
 * @title Calculation of product prices
 *
 * @description This event allows to intercept the product price calculation process.
 *
 * @codeCoverageIgnore
 *
 * @extends Extension<void>
 */
#[Package('inventory')]
final class ProductPriceCalculationExtension extends Extension
{
    public const NAME = 'product.calculate-prices';

    /**
     * @internal shopware owns the __constructor, but the properties are public API
     */
    public function __construct(
        /**
         * @public
         *
         * @description The products which has to be calculated
         *
         * @var iterable<Entity> $products
         */
        public readonly iterable $products,

        /**
         * @public
         *
         * @description Allows you to access to the current customer/sales-channel context
         */
        public readonly SalesChannelContext $context
    ) {
    }
}
