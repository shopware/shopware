<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\SalesChannel;

use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\SuccessResponse;

/**
 * This route can be used to change profile information about the logged-in user
 * The required fields are "salutationId", "firstName" and "lastName"
 */
abstract class AbstractChangeCustomerProfileRoute
{
    abstract public function getDecorated(): AbstractChangeCustomerProfileRoute;

    /**
     * @deprecated tag:v6.4.0 - Parameter $customer will be mandatory in future implementation
     */
    abstract public function change(RequestDataBag $data, SalesChannelContext $context/*, CustomerEntity $customer */): SuccessResponse;
}
