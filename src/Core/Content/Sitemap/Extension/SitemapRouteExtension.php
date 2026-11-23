<?php declare(strict_types=1);

namespace Shopware\Core\Content\Sitemap\Extension;

use Shopware\Core\Content\Sitemap\SalesChannel\SitemapRouteResponse;
use Shopware\Core\Framework\Extensions\Extension;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * @public
 *
 * @codeCoverageIgnore
 *
 * @extends Extension<SitemapRouteResponse>
 */
#[Package('discovery')]
final class SitemapRouteExtension extends Extension
{
    public const NAME = 'sitemap-route.load';

    /**
     * @internal Shopware owns the constructor; the properties are public API.
     */
    public function __construct(
        public readonly Request $request,
        public readonly SalesChannelContext $context,
    ) {
    }
}
