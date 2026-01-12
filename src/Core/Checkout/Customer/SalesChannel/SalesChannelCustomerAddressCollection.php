<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\SalesChannel;

use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressCollection;
use Shopware\Core\Framework\Log\Package;

#[Package('checkout')]
class SalesChannelCustomerAddressCollection extends CustomerAddressCollection
{
    protected function getExpectedClass(): string
    {
        return SalesChannelCustomerAddressEntity::class;
    }
}
