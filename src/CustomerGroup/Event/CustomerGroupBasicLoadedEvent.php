<?php declare(strict_types=1);

namespace Shopware\CustomerGroup\Event;

use Shopware\Context\Struct\TranslationContext;
use Shopware\CustomerGroup\Struct\CustomerGroupBasicCollection;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\Event\NestedEventCollection;

class CustomerGroupBasicLoadedEvent extends NestedEvent
{
    const NAME = 'customer_group.basic.loaded';

    /**
     * @var CustomerGroupBasicCollection
     */
    protected $customerGroups;

    /**
     * @var TranslationContext
     */
    protected $context;

    public function __construct(CustomerGroupBasicCollection $customerGroups, TranslationContext $context)
    {
        $this->customerGroups = $customerGroups;
        $this->context = $context;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getCustomerGroups(): CustomerGroupBasicCollection
    {
        return $this->customerGroups;
    }

    public function getContext(): TranslationContext
    {
        return $this->context;
    }

    public function getEvents(): ?NestedEventCollection
    {
        $events = [];

        return new NestedEventCollection($events);
    }
}
