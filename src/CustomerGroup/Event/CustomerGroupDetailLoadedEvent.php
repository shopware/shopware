<?php declare(strict_types=1);

namespace Shopware\CustomerGroup\Event;

use Shopware\Context\Struct\TranslationContext;
use Shopware\CustomerGroup\Struct\CustomerGroupDetailCollection;
use Shopware\CustomerGroupDiscount\Event\CustomerGroupDiscountBasicLoadedEvent;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\Event\NestedEventCollection;

class CustomerGroupDetailLoadedEvent extends NestedEvent
{
    const NAME = 'customer_group.detail.loaded';

    /**
     * @var CustomerGroupDetailCollection
     */
    protected $customerGroups;

    /**
     * @var TranslationContext
     */
    protected $context;

    public function __construct(CustomerGroupDetailCollection $customerGroups, TranslationContext $context)
    {
        $this->customerGroups = $customerGroups;
        $this->context = $context;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getCustomerGroups(): CustomerGroupDetailCollection
    {
        return $this->customerGroups;
    }

    public function getContext(): TranslationContext
    {
        return $this->context;
    }

    public function getEvents(): ?NestedEventCollection
    {
        $events = [
            new CustomerGroupBasicLoadedEvent($this->customerGroups, $this->context),
        ];

        if ($this->customerGroups->getDiscounts()->count() > 0) {
            $events[] = new CustomerGroupDiscountBasicLoadedEvent($this->customerGroups->getDiscounts(), $this->context);
        }

        return new NestedEventCollection($events);
    }
}
