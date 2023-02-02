<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\SalesChannel;

use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\ContextTokenResponse;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * This route can be used to logout the current context token
 */
abstract class AbstractLogoutRoute
{
    abstract public function getDecorated(): AbstractLogoutRoute;

    abstract public function logout(SalesChannelContext $context, RequestDataBag $data): ContextTokenResponse;
}
