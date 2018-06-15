<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Aggregate\CustomerGroupDiscount\Event;

use Shopware\Core\Checkout\Customer\Aggregate\CustomerGroup\Event\CustomerGroupBasicLoadedEvent;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerGroupDiscount\Collection\CustomerGroupDiscountDetailCollection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\NestedEvent;
use Shopware\Core\Framework\Event\NestedEventCollection;

class CustomerGroupDiscountDetailLoadedEvent extends NestedEvent
{
    public const NAME = 'customer_group_discount.detail.loaded';

    /**
     * @var Context
     */
    protected $context;

    /**
     * @var CustomerGroupDiscountDetailCollection
     */
    protected $customerGroupDiscounts;

    public function __construct(CustomerGroupDiscountDetailCollection $customerGroupDiscounts, Context $context)
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

    public function getCustomerGroupDiscounts(): CustomerGroupDiscountDetailCollection
    {
        return $this->customerGroupDiscounts;
    }

    public function getEvents(): ?NestedEventCollection
    {
        $events = [];
        if ($this->customerGroupDiscounts->getCustomerGroups()->count() > 0) {
            $events[] = new CustomerGroupBasicLoadedEvent($this->customerGroupDiscounts->getCustomerGroups(), $this->context);
        }

        return new NestedEventCollection($events);
    }
}
