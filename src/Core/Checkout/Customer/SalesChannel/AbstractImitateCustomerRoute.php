<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\SalesChannel;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

#[Package('checkout')]
abstract class AbstractImitateCustomerRoute
{
    abstract public function getDecorated(): AbstractImitateCustomerRoute;

    abstract public function imitateCustomerLogin(Request $request, SalesChannelContext $context): Response;
}
