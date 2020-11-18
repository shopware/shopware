<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\SalesChannel;

use Shopware\Core\System\SalesChannel\SalesChannelContext;

abstract class AbstractCustomerGroupRegistrationSettingsRoute
{
    abstract public function getDecorated(): AbstractCustomerGroupRegistrationSettingsRoute;

    abstract public function load(string $customerGroupId, SalesChannelContext $context): CustomerGroupRegistrationSettingsRouteResponse;
}
