<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\SalesChannel;

use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\System\SalesChannel\NoContentResponse;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * This route can be to switch the current default shipping or billing address
 */
abstract class AbstractSwitchDefaultAddressRoute
{
    public const TYPE_BILLING = 'billing';
    public const TYPE_SHIPPING = 'shipping';

    abstract public function getDecorated(): AbstractSwitchDefaultAddressRoute;

    /**
     * @deprecated tag:v6.4.0 - Parameter $customer will be mandatory in future implementation
     */
    abstract public function swap(string $addressId, string $type, SalesChannelContext $context/*, CustomerEntity $customer*/): NoContentResponse;
}
