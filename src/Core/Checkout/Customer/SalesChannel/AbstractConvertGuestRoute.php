<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\SalesChannel;

use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\Framework\Validation\DataValidationDefinition;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\SuccessResponse;

/**
 * This route is used to set a password for a guest user and convert it to a registered one.
 */
#[Package('checkout')]
abstract class AbstractConvertGuestRoute
{
    abstract public function getDecorated(): AbstractConvertGuestRoute;

    abstract public function convertGuest(RequestDataBag $requestDataBag, SalesChannelContext $context, CustomerEntity $customer, ?DataValidationDefinition $additionalValidationDefinitions = null): SuccessResponse;
}
