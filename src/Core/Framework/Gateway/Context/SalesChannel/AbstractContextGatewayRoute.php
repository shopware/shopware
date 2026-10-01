<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Gateway\Context\SalesChannel;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\ContextTokenResponse;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

#[Package('framework')]
abstract class AbstractContextGatewayRoute
{
    abstract public function getDecorated(): AbstractContextGatewayRoute;

    abstract public function load(Request $request, Cart $cart, SalesChannelContext $context): ContextTokenResponse;
}
