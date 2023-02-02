<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\SalesChannel;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * This route can be used to complete the double optin registration.
 * The required parameters are: "hash" (received from the mail) and "em" (received from the mail)
 */
#[Package('customer-order')]
abstract class AbstractRegisterConfirmRoute
{
    abstract public function getDecorated(): AbstractRegisterConfirmRoute;

    abstract public function confirm(RequestDataBag $dataBag, SalesChannelContext $context): CustomerResponse;
}
