<?php declare(strict_types=1);

namespace Shopware\Core\Content\LandingPage\Extension;

use Shopware\Core\Content\LandingPage\SalesChannel\LandingPageRouteResponse;
use Shopware\Core\Framework\Extensions\Extension;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * @public
 *
 * @codeCoverageIgnore
 *
 * @extends Extension<LandingPageRouteResponse>
 */
#[Package('discovery')]
final class LandingPageRouteExtension extends Extension
{
    public const NAME = 'landing-page-route.load';

    /**
     * @internal Shopware owns the constructor; the properties are public API.
     */
    public function __construct(
        public readonly string $landingPageId,
        public readonly Request $request,
        public readonly SalesChannelContext $context,
    ) {
    }
}
