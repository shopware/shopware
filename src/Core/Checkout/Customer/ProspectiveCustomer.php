<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;

/**
 * What is known about a visitor who has no customer yet, e.g. the address typed into a headless checkout form.
 * It only exists on a sales channel context without a customer and is never persisted as a customer.
 */
#[Package('checkout')]
final class ProspectiveCustomer extends Struct
{
    /**
     * @internal
     */
    public function __construct(
        protected ProspectiveAddress $shippingAddress,
        protected ?ProspectiveAddress $billingAddress,
        private readonly CustomerEntity $ruleCustomer,
    ) {
    }

    public function getShippingAddress(): ProspectiveAddress
    {
        return $this->shippingAddress;
    }

    /**
     * @return ProspectiveAddress|null null when the billing address is the shipping address
     */
    public function getBillingAddress(): ?ProspectiveAddress
    {
        return $this->billingAddress;
    }

    /**
     * @internal only rule matching may see this customer, it does not exist in the database
     */
    public function getRuleCustomer(): CustomerEntity
    {
        return $this->ruleCustomer;
    }

    public function jsonSerialize(): array
    {
        $vars = parent::jsonSerialize();
        unset($vars['ruleCustomer']);

        return $vars;
    }

    public function getApiAlias(): string
    {
        return 'prospective_customer';
    }
}
