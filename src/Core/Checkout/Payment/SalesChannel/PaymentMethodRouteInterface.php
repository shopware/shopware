<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Payment\SalesChannel;

use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

interface PaymentMethodRouteInterface
{
    public function load(Request $request, SalesChannelContext $context): PaymentMethodRouteResponse;
}
