<?php declare(strict_types=1);

namespace Shopware\Checkout\Customer\Aggregate\CustomerGroup\Event;

use Shopware\Framework\Context;
use Shopware\Checkout\Customer\Aggregate\CustomerGroup\Collection\CustomerGroupDetailCollection;
use Shopware\Checkout\Customer\Aggregate\CustomerGroupDiscount\Event\CustomerGroupDiscountBasicLoadedEvent;
use Shopware\Checkout\Customer\Aggregate\CustomerGroupTranslation\Event\CustomerGroupTranslationBasicLoadedEvent;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\Event\NestedEventCollection;

class CustomerGroupDetailLoadedEvent extends NestedEvent
{
    public const NAME = 'customer_group.detail.loaded';

    /**
     * @var Context
     */
    protected $context;

    /**
     * @var \Shopware\Checkout\Customer\Aggregate\CustomerGroup\Collection\CustomerGroupDetailCollection
     */
    protected $customerGroups;

    public function __construct(CustomerGroupDetailCollection $customerGroups, Context $context)
    {
        $this->context = $context;
        $this->customerGroups = $customerGroups;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    public function getCustomerGroups(): CustomerGroupDetailCollection
    {
        return $this->customerGroups;
    }

    public function getEvents(): ?NestedEventCollection
    {
        $events = [];
        if ($this->customerGroups->getDiscounts()->count() > 0) {
            $events[] = new CustomerGroupDiscountBasicLoadedEvent($this->customerGroups->getDiscounts(), $this->context);
        }
        if ($this->customerGroups->getTranslations()->count() > 0) {
            $events[] = new CustomerGroupTranslationBasicLoadedEvent($this->customerGroups->getTranslations(), $this->context);
        }

        return new NestedEventCollection($events);
    }
}
