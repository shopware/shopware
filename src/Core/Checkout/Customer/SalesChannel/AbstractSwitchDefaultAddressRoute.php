<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\SalesChannel;

use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\NoContentResponse;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * This route can be to switch the current default shipping or billing address
 */
#[Package('customer-order')]
abstract class AbstractSwitchDefaultAddressRoute
{
    final public const TYPE_BILLING = 'billing';
    final public const TYPE_SHIPPING = 'shipping';

    abstract public function getDecorated(): AbstractSwitchDefaultAddressRoute;

    abstract public function swap(string $addressId, string $type, SalesChannelContext $context, CustomerEntity $customer): NoContentResponse;
}
