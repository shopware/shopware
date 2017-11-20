<?php declare(strict_types=1);

namespace Shopware\Customer\Event\CustomerGroupDiscount;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Customer\Collection\CustomerGroupDiscountDetailCollection;
use Shopware\Customer\Event\CustomerGroup\CustomerGroupBasicLoadedEvent;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\Event\NestedEventCollection;

class CustomerGroupDiscountDetailLoadedEvent extends NestedEvent
{
    const NAME = 'customer_group_discount.detail.loaded';

    /**
     * @var TranslationContext
     */
    protected $context;

    /**
     * @var CustomerGroupDiscountDetailCollection
     */
    protected $customerGroupDiscounts;

    public function __construct(CustomerGroupDiscountDetailCollection $customerGroupDiscounts, TranslationContext $context)
    {
        $this->context = $context;
        $this->customerGroupDiscounts = $customerGroupDiscounts;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): TranslationContext
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
