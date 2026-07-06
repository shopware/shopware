<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\SalesChannel\PurchaseLimit;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

#[Package('inventory')]
abstract class AbstractProductPurchaseLimitRoute
{
    abstract public function getDecorated(): AbstractProductPurchaseLimitRoute;

    abstract public function readProductsPurchaseLimit(Request $request, SalesChannelContext $context): ProductPurchaseLimitRouteResponse;
}
