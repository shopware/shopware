<?php declare(strict_types=1);

namespace Shopware\Core\Content\RevocationRequest\Extension;

use Shopware\Core\Content\RevocationRequest\SalesChannel\RevocationRequestRouteResponse;
use Shopware\Core\Framework\Extensions\Extension;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * @public
 *
 * @codeCoverageIgnore
 *
 * @extends Extension<RevocationRequestRouteResponse>
 */
#[Package('after-sales')]
final class RevocationRequestRouteExtension extends Extension
{
    public const NAME = 'revocation-request-route.request';

    /**
     * @internal Shopware owns the constructor; the properties are public API.
     */
    public function __construct(
        public readonly RequestDataBag $dataBag,
        public readonly SalesChannelContext $context,
    ) {
    }
}
