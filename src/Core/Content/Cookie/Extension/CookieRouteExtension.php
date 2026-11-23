<?php declare(strict_types=1);

namespace Shopware\Core\Content\Cookie\Extension;

use Shopware\Core\Content\Cookie\SalesChannel\CookieRouteResponse;
use Shopware\Core\Framework\Extensions\Extension;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * @public
 *
 * @experimental stableVersion:v6.8.0 feature:COOKIE_GROUPS_STORE_API
 *
 * @codeCoverageIgnore
 *
 * @extends Extension<CookieRouteResponse>
 */
#[Package('discovery')]
final class CookieRouteExtension extends Extension
{
    public const NAME = 'cookie-route.get-cookie-groups';

    /**
     * @internal Shopware owns the constructor; the properties are public API.
     */
    public function __construct(
        public readonly Request $request,
        public readonly SalesChannelContext $salesChannelContext,
    ) {
    }
}
