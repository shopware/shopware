<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\SalesChannel;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Response;

/**
 * This route is used to login and get a new context token
 * The required parameters are "email" and "password"
 */
#[Package('checkout')]
abstract class AbstractLoginRoute
{
    abstract public function getDecorated(): AbstractLoginRoute;

    abstract public function login(LoginCustomerRequestDTO $data, SalesChannelContext $context): Response;
}
