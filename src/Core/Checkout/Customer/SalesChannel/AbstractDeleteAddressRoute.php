<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\SalesChannel;

use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\System\SalesChannel\NoContentResponse;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * This route can be used to delete addresses
 */
abstract class AbstractDeleteAddressRoute
{
    abstract public function getDecorated(): AbstractDeleteAddressRoute;

    /**
     * @deprecated tag:v6.4.0 - Parameter $customer will be mandatory in future implementation
     */
    abstract public function delete(string $addressId, SalesChannelContext $context/*, CustomerEntity $customer*/): NoContentResponse;
}
