<?php declare(strict_types=1);

namespace Shopware\Core\System\Tax\TaxRuleType;

use Shopware\Core\Checkout\Cart\Delivery\Struct\ShippingLocation;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\System\Tax\Aggregate\TaxRule\TaxRuleEntity;

interface TaxRuleTypeFilterInterface
{
    public function match(TaxRuleEntity $taxRuleEntity, ?CustomerEntity $customerEntity, ShippingLocation $shippingLocation): bool;
}
