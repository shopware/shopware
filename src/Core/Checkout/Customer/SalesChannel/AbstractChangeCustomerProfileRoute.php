<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\SalesChannel;

use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\SuccessResponse;

/**
 * This route can be used to change profile information about the logged-in user
 * The required fields are "salutationId", "firstName" and "lastName"
 * "firstName" and "lastName" may be omitted for a commercial account in a shop that turned
 * "core.loginRegistration.nameFieldsRequiredForCompanyAccounts" off. The company then takes their place and
 * is required instead.
 */
#[Package('checkout')]
abstract class AbstractChangeCustomerProfileRoute
{
    abstract public function getDecorated(): AbstractChangeCustomerProfileRoute;

    abstract public function change(RequestDataBag $data, SalesChannelContext $context, CustomerEntity $customer): SuccessResponse;
}
