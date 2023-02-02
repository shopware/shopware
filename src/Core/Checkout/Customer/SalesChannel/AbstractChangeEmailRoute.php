<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\SalesChannel;

use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\SuccessResponse;

/**
 * This route is used to change the email of a logged-in user
 * The required fields are: "password", "email" and "emailConfirmation"
 */
#[Package('customer-order')]
abstract class AbstractChangeEmailRoute
{
    abstract public function getDecorated(): AbstractChangeEmailRoute;

    abstract public function change(RequestDataBag $requestDataBag, SalesChannelContext $context, CustomerEntity $customer): SuccessResponse;
}
