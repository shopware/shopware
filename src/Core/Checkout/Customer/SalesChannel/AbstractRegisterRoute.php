<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\SalesChannel;

use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\Framework\Validation\DataValidationDefinition;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * This route is used for customer registration
 * The required parameters are: "salutationId", "firstName", "lastName", "email", "password", "billingAddress" and "storefrontUrl"
 * The "billingAddress" should has required parameters: "salutationId", "firstName", "lastName", "street", "zipcode", "city", "countyId".
 */
abstract class AbstractRegisterRoute
{
    abstract public function getDecorated(): AbstractRegisterRoute;

    abstract public function register(RequestDataBag $data, SalesChannelContext $context, bool $validateStorefrontUrl = true, ?DataValidationDefinition $additionalValidationDefinitions = null): CustomerResponse;
}
