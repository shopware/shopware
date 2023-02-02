<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\SalesChannel;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\ContextTokenResponse;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * This route is used to login and get a new context token
 * The required parameters are "email" and "password"
 */
#[Package('customer-order')]
abstract class AbstractLoginRoute
{
    abstract public function getDecorated(): AbstractLoginRoute;

    abstract public function login(RequestDataBag $data, SalesChannelContext $context): ContextTokenResponse;
}
