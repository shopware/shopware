<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\SalesChannel;

use Shopware\Core\Checkout\Customer\CustomerEntity;
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
     * Marked final so implementers only need to implement swapWithResponse.
     *
     * Deprecation: plugin authors should migrate to swapWithResponse and update callers.
     */
    final public function swap(string $addressId, string $type, SalesChannelContext $context, CustomerEntity $customer): NoContentResponse
    {
        @trigger_deprecation('AbstractSwitchDefaultAddressRoute::swap() is deprecated, use swapWithResponse() which returns SwitchDefaultAddressRouteResponse', \E_USER_DEPRECATED);

        $this->swapWithResponse($addressId, $type, $context, $customer);

        return new NoContentResponse();
    }
}
