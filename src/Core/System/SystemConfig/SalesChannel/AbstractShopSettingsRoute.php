<?php declare(strict_types=1);

namespace Shopware\Core\System\SystemConfig\SalesChannel;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

#[Package('framework')]
abstract class AbstractShopSettingsRoute
{
    abstract public function getDecorated(): AbstractShopSettingsRoute;

    abstract public function load(SalesChannelContext $context): ShopSettingsRouteResponse;
}
