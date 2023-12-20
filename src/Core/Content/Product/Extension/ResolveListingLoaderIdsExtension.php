<?php

namespace Shopware\Core\Content\Product\Extension;

use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;
use Shopware\Core\Framework\Extensions\Extension;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * @public this class is used as type-hint for all event listeners, so the class string is "public consumable" API
 *
 * @title Determination of the listing product ids
 * @description This event allows intercepting the listing process, when the product ids should be determined for the current category page and the applied filter.
 * @example("ResolveListingIdsByYourOwnExample.php")
 */
final class ResolveListingLoaderIdsExtension extends Extension
{
    /**
     * {@inheritdoc}
     */
    public static function name(): string
    {
        return 'listing-loader.resolve-listing-ids';
    }

    /**
     * @public
     * @description The id search result acts as a DTO for the product ids. It contains the ids and the total count of the products. Also all added extensions will be transferred to the next event and to the final listing result object
     */
    public function result(): IdSearchResult
    {
        return $this->result;
    }

    /**
     * @internal shopware owns the __constructor, but the properties are public API
     */
    public function __construct(
        /**
         * @public
         * @description The criteria which should be used to load the products. Is also contains the selected customer filter
         */
        public Criteria $criteria,

        /**
         * @public
         * @description Allows you to access to the current customer/sales-channel context
         */
        public SalesChannelContext $context
    ) {
    }
}
