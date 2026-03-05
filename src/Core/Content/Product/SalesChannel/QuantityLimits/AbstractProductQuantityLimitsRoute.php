<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\SalesChannel\QuantityLimits;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

#[Package('inventory')]
abstract class AbstractProductQuantityLimitsRoute
{
    abstract public function getDecorated(): AbstractProductQuantityLimitsRoute;

    abstract public function load(string $productId, Request $request, SalesChannelContext $context): ProductQuantityLimitsRouteResponse;
}
