<?php declare(strict_types=1);

namespace Shopware\Core\System\SystemConfig\Extension;

use Shopware\Core\Framework\Extensions\Extension;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SalesChannel\ShopSettingsRouteResponse;

/**
 * @public
 *
 * @codeCoverageIgnore
 *
 * @extends Extension<ShopSettingsRouteResponse>
 */
#[Package('framework')]
final class ShopSettingsRouteExtension extends Extension
{
    public const NAME = 'shop-settings-route.load';

    /**
     * @internal Shopware owns the constructor; the properties are public API.
     */
    public function __construct(
        public readonly SalesChannelContext $context,
    ) {
    }
}
