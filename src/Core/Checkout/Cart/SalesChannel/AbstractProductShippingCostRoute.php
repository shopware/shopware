<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\SalesChannel;

use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

#[Package('checkout')]
abstract class AbstractProductShippingCostRoute
{
    abstract public function getDecorated(): AbstractProductShippingCostRoute;

    abstract public function shippingCostsByProduct(string $productId, Criteria $criteria, SalesChannelContext $salesChannelContext): ShippingCostRouteResponse;
}
