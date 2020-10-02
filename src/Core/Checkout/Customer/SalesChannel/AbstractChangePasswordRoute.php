<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\SalesChannel;

use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * This route is used to change the password of a logged-in user
 * The required fields are: "password", "newPassword" and "newPasswordConfirm"
 */
abstract class AbstractChangePasswordRoute
{
    abstract public function getDecorated(): AbstractChangePasswordRoute;

    abstract public function change(RequestDataBag $requestDataBag, SalesChannelContext $context);
}
