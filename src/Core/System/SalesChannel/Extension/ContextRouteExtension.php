<?php declare(strict_types=1);

namespace Shopware\Core\System\SalesChannel\Extension;

use Shopware\Core\Framework\Extensions\Extension;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannel\ContextLoadRouteResponse;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * @public
 *
 * @codeCoverageIgnore
 *
 * @extends Extension<ContextLoadRouteResponse>
 */
#[Package('framework')]
final class ContextRouteExtension extends Extension
{
    public const NAME = 'context-route.load';

    /**
     * @internal Shopware owns the constructor; the properties are public API.
     */
    public function __construct(
        public readonly SalesChannelContext $context,
    ) {
    }
}
