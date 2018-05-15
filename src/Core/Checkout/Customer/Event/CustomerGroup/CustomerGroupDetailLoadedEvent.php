<?php declare(strict_types=1);

namespace Shopware\Checkout\Customer\Event\CustomerGroup;

use Shopware\Checkout\Customer\Collection\CustomerGroupDetailCollection;
use Shopware\Checkout\Customer\Event\CustomerGroupDiscount\CustomerGroupDiscountBasicLoadedEvent;
use Shopware\Checkout\Customer\Event\CustomerGroupTranslation\CustomerGroupTranslationBasicLoadedEvent;
use Shopware\Context\Struct\ApplicationContext;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\Event\NestedEventCollection;

class CustomerGroupDetailLoadedEvent extends NestedEvent
{
    public const NAME = 'customer_group.detail.loaded';

    /**
     * @var ApplicationContext
     */
    protected $context;

    /**
     * @var CustomerGroupDetailCollection
     */
    protected $customerGroups;

    public function __construct(CustomerGroupDetailCollection $customerGroups, ApplicationContext $context)
    {
        $this->context = $context;
        $this->customerGroups = $customerGroups;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): ApplicationContext
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
