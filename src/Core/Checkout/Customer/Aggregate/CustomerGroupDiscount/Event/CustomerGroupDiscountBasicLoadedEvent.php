<?php declare(strict_types=1);

namespace Shopware\Checkout\Customer\Aggregate\CustomerGroupDiscount\Event;

use Shopware\Framework\Context;
use Shopware\Checkout\Customer\Aggregate\CustomerGroupDiscount\Collection\CustomerGroupDiscountBasicCollection;
use Shopware\Framework\Event\NestedEvent;

class CustomerGroupDiscountBasicLoadedEvent extends NestedEvent
{
    public const NAME = 'customer_group_discount.basic.loaded';

    /**
     * @var Context
     */
    protected $context;

    /**
     * @var \Shopware\Checkout\Customer\Aggregate\CustomerGroupDiscount\Collection\CustomerGroupDiscountBasicCollection
     */
    protected $customerGroupDiscounts;

    public function __construct(CustomerGroupDiscountBasicCollection $customerGroupDiscounts, Context $context)
    {
        $this->context = $context;
        $this->customerGroupDiscounts = $customerGroupDiscounts;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    public function getCustomerGroupDiscounts(): CustomerGroupDiscountBasicCollection
    {
        return $this->customerGroupDiscounts;
    }
}
