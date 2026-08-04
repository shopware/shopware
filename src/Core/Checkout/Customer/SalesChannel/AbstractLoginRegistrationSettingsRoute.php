<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\SalesChannel;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * This route can be used to fetch the login/registration settings of the current sales channel,
 * as configured in the administration under Settings > Log-in & sign-up.
 */
#[Package('checkout')]
abstract class AbstractLoginRegistrationSettingsRoute
{
    abstract public function getDecorated(): AbstractLoginRegistrationSettingsRoute;

    abstract public function load(SalesChannelContext $context): LoginRegistrationSettingsRouteResponse;
}
