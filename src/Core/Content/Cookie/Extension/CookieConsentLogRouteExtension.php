<?php declare(strict_types=1);

namespace Shopware\Core\Content\Cookie\Extension;

use Shopware\Core\Content\Cookie\SalesChannel\CookieConsentLogPayload;
use Shopware\Core\Framework\Extensions\Extension;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\NoContentResponse;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * @public
 *
 * @codeCoverageIgnore
 *
 * @extends Extension<NoContentResponse>
 */
#[Package('framework')]
final class CookieConsentLogRouteExtension extends Extension
{
    public const NAME = 'cookie-consent-log-route.log';

    /**
     * @internal Shopware owns the constructor; the properties are public API.
     */
    public function __construct(
        public readonly CookieConsentLogPayload $payload,
        public readonly Request $request,
        public readonly SalesChannelContext $salesChannelContext,
    ) {
    }
}
