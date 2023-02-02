<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\SalesChannel;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\SuccessResponse;

/**
 * This route is used handle the password reset form
 * The required parameters are: "hash" (received from the mail), "newPassword" and "newPasswordConfirm"
 */
#[Package('customer-order')]
abstract class AbstractResetPasswordRoute
{
    abstract public function getDecorated(): AbstractResetPasswordRoute;

    abstract public function resetPassword(RequestDataBag $data, SalesChannelContext $context): SuccessResponse;
}
