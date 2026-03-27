<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\SalesChannel;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

#[Package('checkout')]
abstract class AbstractDeliveryCostRoute
{
    abstract public function getDecorated(): AbstractDeliveryCostRoute;

    abstract public function deliveryCostsByProduct(Request $request, string $productId, SalesChannelContext $salesChannelContext): DeliveryCostRouteResponse;

    abstract public function deliveryCostsCart(SalesChannelContext $salesChannelContext): DeliveryCostRouteResponse;
}
