<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\SalesChannel;

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

    abstract public function change(RequestDataBag $data, SalesChannelContext $context): SuccessResponse;
}
