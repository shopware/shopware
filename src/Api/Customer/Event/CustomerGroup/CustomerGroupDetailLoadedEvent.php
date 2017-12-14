<?php declare(strict_types=1);

namespace Shopware\Api\Customer\Event\CustomerGroup;

use Shopware\Api\Customer\Collection\CustomerGroupDetailCollection;
use Shopware\Api\Customer\Event\Customer\CustomerBasicLoadedEvent;
use Shopware\Api\Customer\Event\CustomerGroupDiscount\CustomerGroupDiscountBasicLoadedEvent;
use Shopware\Api\Customer\Event\CustomerGroupTranslation\CustomerGroupTranslationBasicLoadedEvent;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\Event\NestedEventCollection;

class CustomerGroupDetailLoadedEvent extends NestedEvent
{
    const NAME = 'customer_group.detail.loaded';

    /**
     * @var TranslationContext
     */
    protected $context;

    /**
     * @var CustomerGroupDetailCollection
     */
    protected $customerGroups;

    public function __construct(CustomerGroupDetailCollection $customerGroups, TranslationContext $context)
    {
        $this->context = $context;
        $this->customerGroups = $customerGroups;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): TranslationContext
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
        if ($this->customerGroups->getCustomers()->count() > 0) {
            $events[] = new CustomerBasicLoadedEvent($this->customerGroups->getCustomers(), $this->context);
        }
        if ($this->customerGroups->getDiscounts()->count() > 0) {
            $events[] = new CustomerGroupDiscountBasicLoadedEvent($this->customerGroups->getDiscounts(), $this->context);
        }
        if ($this->customerGroups->getTranslations()->count() > 0) {
            $events[] = new CustomerGroupTranslationBasicLoadedEvent($this->customerGroups->getTranslations(), $this->context);
        }

        return new NestedEventCollection($events);
    }
}
