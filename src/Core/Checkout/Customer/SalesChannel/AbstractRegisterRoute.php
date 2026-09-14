<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\SalesChannel;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\Framework\Validation\DataValidationDefinition;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * This route is used for customer registration
 * The required parameters are: "salutationId", "firstName", "lastName", "email", "password" and "billingAddress"
 * "storefrontUrl" is additionally required when double opt-in registration is enabled
 * The "billingAddress" should has required parameters: "salutationId", "firstName", "lastName", "street", "zipcode", "city", "countyId".
 * "firstName" and "lastName" may be omitted, at the top level and on the billing address, for a commercial
 * registration when the shop shows the account type selection and turned either
 * "core.loginRegistration.showNameFieldsForCompanyAccounts" or
 * "core.loginRegistration.nameFieldsRequiredForCompanyAccounts" off. Without the account type selection they
 * stay required. The company then takes their place and is required instead. A separate "shippingAddress"
 * keeps its own names.
 */
#[Package('checkout')]
abstract class AbstractRegisterRoute
{
    abstract public function getDecorated(): AbstractRegisterRoute;

    abstract public function register(RequestDataBag $data, SalesChannelContext $context, bool $validateStorefrontUrl = true, ?DataValidationDefinition $additionalValidationDefinitions = null): CustomerResponse;
}
