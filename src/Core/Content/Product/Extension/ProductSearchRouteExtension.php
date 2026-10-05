<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Extension;

use Shopware\Core\Content\Product\SalesChannel\Search\ProductSearchRouteResponse;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Extensions\Extension;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * @public This class is used as type-hint for all event listeners, so the class string is "public consumable" API
 *
 * @title Loads the product search result for the store-api search route
 *
 * @description Wraps the whole `/store-api/search` loading. A listener on the `.pre` event may resolve the search itself — e.g. against an external search service or with an enriched result — by assigning `$extension->result` and stopping propagation, short-circuiting the core. `.post` may adjust the loaded result.
 *
 * @codeCoverageIgnore
 *
 * @extends Extension<ProductSearchRouteResponse>
 */
#[Package('inventory')]
final class ProductSearchRouteExtension extends Extension
{
    public const NAME = 'product-search-route.load';

    /**
     * @internal Shopware owns the __constructor, but the properties are public API
     */
    public function __construct(
        /**
         * @public
         *
         * @description The current store-api request
         */
        public readonly Request $request,
        /**
         * @public
         *
         * @description Allows access to the current sales-channel context
         */
        public readonly SalesChannelContext $context,
        /**
         * @public
         *
         * @description The criteria used for the product search
         */
        public readonly Criteria $criteria,
    ) {
    }
}
