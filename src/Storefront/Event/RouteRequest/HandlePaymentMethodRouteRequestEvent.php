<?php declare(strict_types=1);

namespace Shopware\Storefront\Event\RouteRequest;

use Shopware\Core\Framework\Log\Package;

#[Package('checkout')]
class HandlePaymentMethodRouteRequestEvent extends RouteRequestEvent
{
}
