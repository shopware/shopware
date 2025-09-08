<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Extension;

use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\AggregationResultCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;
use Shopware\Core\Framework\Extensions\Extension;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * @public this class is used as type-hint for all event listeners, so the class string is "public consumable" API
 *
 * @title Determination of the listing product aggregations
 *
 * @description This event allows intercepting the listing process, when the product aggregations should be determined for the current category page and the applied filter.
 *
 * @codeCoverageIgnore
 *
 * @extends Extension<?AggregationResultCollection>
 */
#[Package('inventory')]
final class ResolveListingAggregationsExtension extends Extension
{
    public const NAME = 'listing-loader.resolve-listing-aggregations';

    /**
     * @internal shopware owns the __constructor, but the properties are public API
     */
    public function __construct(
        /**
         * @public
         *
         * @description The criteria which should be used to load the product aggregations. Is also containing the selected customer filter
         */
        public Criteria $criteria,

        /**
         * @public
         *
         * @description Allows you to access to the current customer/sales-channel context
         */
        public SalesChannelContext $context,

        /**
         * @public
         *
         * @description The result of the id search result, which contains the product ids that are listed in the current category with the applied filter
         */
        public IdSearchResult $idSearchResult,
    ) {
    }
}
