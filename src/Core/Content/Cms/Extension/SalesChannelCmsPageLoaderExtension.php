<?php declare(strict_types=1);

namespace Shopware\Core\Content\Cms\Extension;

use Shopware\Core\Content\Cms\CmsPageCollection;
use Shopware\Core\Content\Cms\DataResolver\ResolverContext\ResolverContext;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Extensions\Extension;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * @public This class is used as type-hint for all event listeners, so the class string is "public consumable" API
 *
 * @title Loads the CMS page(s) for a storefront request
 *
 * @description Wraps the whole CMS page loading. A listener on the `.pre` event may resolve the pages itself — e.g. a customer-group specific layout, an A/B variant or an external source — by assigning `$extension->result` and stopping propagation, short-circuiting the core loader. `.post` may adjust the loaded result.
 *
 * @codeCoverageIgnore
 *
 * @extends Extension<EntitySearchResult<CmsPageCollection>>
 */
#[Package('discovery')]
final class SalesChannelCmsPageLoaderExtension extends Extension
{
    public const NAME = 'cms-page-loader.load';

    /**
     * @internal Shopware owns the __constructor, but the properties are public API
     *
     * @param array<string, mixed>|null $config
     */
    public function __construct(
        /**
         * @public
         *
         * @description The current storefront request
         */
        public readonly Request $request,
        /**
         * @public
         *
         * @description The criteria used to load the CMS page(s)
         */
        public readonly Criteria $criteria,
        /**
         * @public
         *
         * @description Allows access to the current sales-channel context
         */
        public readonly SalesChannelContext $context,
        /**
         * @public
         *
         * @description Optional per-page slot config overrides
         */
        public readonly ?array $config = null,
        /**
         * @public
         *
         * @description Optional resolver context used to resolve the slot data
         */
        public readonly ?ResolverContext $resolverContext = null,
    ) {
    }
}
