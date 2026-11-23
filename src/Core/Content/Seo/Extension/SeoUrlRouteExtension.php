<?php declare(strict_types=1);

namespace Shopware\Core\Content\Seo\Extension;

use Shopware\Core\Content\Seo\SalesChannel\SeoUrlRouteResponse;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Extensions\Extension;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * @public
 *
 * @codeCoverageIgnore
 *
 * @extends Extension<SeoUrlRouteResponse>
 */
#[Package('inventory')]
final class SeoUrlRouteExtension extends Extension
{
    public const NAME = 'seo-url-route.load';

    /**
     * @internal Shopware owns the constructor; the properties are public API.
     */
    public function __construct(
        public readonly Request $request,
        public readonly SalesChannelContext $context,
        public readonly Criteria $criteria,
    ) {
    }
}
