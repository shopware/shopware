<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Extension;

use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Extensions\Extension;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * @extends Extension<Criteria>
 */
#[Package('inventory')]
final class ProductListingCriteriaExtension extends Extension
{
    public const NAME = 'product.listing.criteria';

    /**
     * @internal shopware owns the __constructor, but the properties are public API
     */
    public function __construct(
        /**
         * @public
         *
         * @description The criteria which should be used to load the products. Is also containing the selected customer filter
         */
        public readonly Criteria $criteria,

        /**
         * @public
         *
         * @description Allows you to access to the current customer/sales-channel context
         */
        public readonly SalesChannelContext $context,
        /**
         * @public
         *
         * @description Contains current category id
         */
        public readonly string $categoryId,
    ) {
    }
}
