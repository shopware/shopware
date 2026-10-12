<?php declare(strict_types=1);

namespace Shopware\Core\Content\Category\Extension;

use Shopware\Core\Content\Category\SalesChannel\NavigationRouteResponse;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Extensions\Extension;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * @public This class is used as type-hint for all event listeners, so the class string is "public consumable" API
 *
 * @title Loads the navigation categories for the store-api navigation route
 *
 * @description Wraps the `/store-api/navigation` loading. A listener on the `.pre` event may resolve the categories itself — e.g. a customer-group filtered tree, custom child counts, or an external source — by assigning `$extension->result` and stopping propagation, short-circuiting the core. `.post` may enrich the loaded categories.
 *
 * @codeCoverageIgnore
 *
 * @extends Extension<NavigationRouteResponse>
 */
#[Package('discovery')]
final class NavigationRouteExtension extends Extension
{
    public const NAME = 'navigation-route.load';

    /**
     * @internal Shopware owns the __constructor, but the properties are public API
     */
    public function __construct(
        /**
         * @public
         *
         * @description The active category id
         */
        public readonly string $activeId,
        /**
         * @public
         *
         * @description The root category id of the requested navigation
         */
        public readonly string $rootId,
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
         * @description The criteria used to load the categories
         */
        public readonly Criteria $criteria,
    ) {
    }
}
