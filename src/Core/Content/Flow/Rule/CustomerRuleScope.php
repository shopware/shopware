<?php declare(strict_types=1);

namespace Shopware\Core\Content\Flow\Rule;

use Shopware\Core\Checkout\CheckoutRuleScope;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * @codeCoverageIgnore
 */
#[Package('after-sales')]
class CustomerRuleScope extends CheckoutRuleScope
{
    public function __construct(
        private readonly CustomerEntity $customer,
        SalesChannelContext $context,
    ) {
        parent::__construct($context);
    }

    public function getCustomer(): CustomerEntity
    {
        return $this->customer;
    }
}
