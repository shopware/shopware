<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\SalesChannel;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

#[Package('checkout')]
abstract class AbstractCartLoadRoute
{
    abstract public function getDecorated(): AbstractCartLoadRoute;

    abstract public function load(Request $request, SalesChannelContext $context): CartResponse;
}
