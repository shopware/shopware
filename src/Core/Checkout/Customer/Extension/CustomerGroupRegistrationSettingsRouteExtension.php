<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Extension;

use Shopware\Core\Checkout\Customer\SalesChannel\CustomerGroupRegistrationSettingsRouteResponse;
use Shopware\Core\Framework\Extensions\Extension;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * @public
 *
 * @codeCoverageIgnore
 *
 * @extends Extension<CustomerGroupRegistrationSettingsRouteResponse>
 */
#[Package('checkout')]
final class CustomerGroupRegistrationSettingsRouteExtension extends Extension
{
    public const NAME = 'customer-group-registration-settings-route.load';

    /**
     * @internal Shopware owns the constructor; the properties are public API.
     */
    public function __construct(
        public readonly string $customerGroupId,
        public readonly SalesChannelContext $context,
    ) {
    }
}
