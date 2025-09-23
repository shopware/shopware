<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\SalesChannel;

use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\NoContentResponse;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * This route can be to switch the current default shipping or billing address
 */
#[Package('checkout')]
abstract class AbstractSwitchDefaultAddressRoute
{
    final public const TYPE_BILLING = 'billing';
    final public const TYPE_SHIPPING = 'shipping';

    abstract public function getDecorated(): AbstractSwitchDefaultAddressRoute;

    /**
     * New API: returns the richer response containing the address collection.
     */
    abstract public function swapWithResponse(string $addressId, string $type, SalesChannelContext $context, CustomerEntity $customer): SwitchDefaultAddressRouteResponse;

    /**
     * Backwards-compatible shim. Delegates to swapWithResponse and returns NoContentResponse.
     * Marked non-final to avoid introducing an unexpected final method BC break.
     *
     * Deprecation: plugin authors should migrate to swapWithResponse and update callers.
     */
    public function swap(string $addressId, string $type, SalesChannelContext $context, CustomerEntity $customer): NoContentResponse
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', Feature::deprecatedMethodMessage(self::class, 'swap', 'v6.8.0.0', 'swapWithResponse'));

        $this->swapWithResponse($addressId, $type, $context, $customer);

        return new NoContentResponse();
    }
}
