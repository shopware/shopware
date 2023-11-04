<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\SalesChannel;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\SuccessResponse;

/**
 * This route is used to send a password recovery mail
 * The required parameters are: "email" and "storefrontUrl"
 * The process can be completed with the hash in the Route Shopware\Core\Checkout\Customer\SalesChannel\AbstractResetPasswordRoute
 */
#[Package('customer-order')]
abstract class AbstractSendPasswordRecoveryMailRoute
{
    abstract public function getDecorated(): AbstractSendPasswordRecoveryMailRoute;

    abstract public function sendRecoveryMail(RequestDataBag $data, SalesChannelContext $context, bool $validateStorefrontUrl = true): SuccessResponse;
}
