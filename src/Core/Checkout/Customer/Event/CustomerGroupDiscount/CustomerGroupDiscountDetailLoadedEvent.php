<?php declare(strict_types=1);

namespace Shopware\Checkout\Customer\Event\CustomerGroupDiscount;

use Shopware\Checkout\Customer\Collection\CustomerGroupDiscountDetailCollection;
use Shopware\Checkout\Customer\Event\CustomerGroup\CustomerGroupBasicLoadedEvent;
use Shopware\Application\Context\Struct\ApplicationContext;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\Event\NestedEventCollection;

class CustomerGroupDiscountDetailLoadedEvent extends NestedEvent
{
    public const NAME = 'customer_group_discount.detail.loaded';

    /**
     * @var ApplicationContext
     */
    protected $context;

    /**
     * @var CustomerGroupDiscountDetailCollection
     */
    protected $customerGroupDiscounts;

    public function __construct(CustomerGroupDiscountDetailCollection $customerGroupDiscounts, ApplicationContext $context)
    {
        $this->context = $context;
        $this->customerGroupDiscounts = $customerGroupDiscounts;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): ApplicationContext
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
