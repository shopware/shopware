<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\SalesChannel;

use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\SuccessResponse;

/**
 * This route is used to change the default payment method of a logged-in user
 */
#[Package('customer-order')]
abstract class AbstractChangePaymentMethodRoute
{
    abstract public function getDecorated(): AbstractChangePaymentMethodRoute;

    abstract public function change(string $paymentMethodId, RequestDataBag $requestDataBag, SalesChannelContext $context, CustomerEntity $customer): SuccessResponse;
}
