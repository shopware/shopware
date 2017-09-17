<?php declare(strict_types=1);

namespace Shopware\CustomerGroupDiscount\Event;

use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\Event\NestedEventCollection;

class CustomerGroupDiscountWrittenEvent extends NestedEvent
{
    const NAME = 'customer_group_discount.written';

    /**
     * @var string[]
     */
    private $customerGroupDiscountUuids;

    /**
     * @var NestedEventCollection
     */
    private $events;

    /**
     * @var array
     */
    private $errors;

    public function __construct(array $customerGroupDiscountUuids, array $errors = [])
    {
        $this->customerGroupDiscountUuids = $customerGroupDiscountUuids;
        $this->events = new NestedEventCollection();
        $this->errors = $errors;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    /**
     * @return string[]
     */
    public function getCustomerGroupDiscountUuids(): array
    {
        return $this->customerGroupDiscountUuids;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    public function hasErrors(): bool
    {
        return count($this->errors) > 0;
    }

    public function addEvent(NestedEvent $event): void
    {
        $this->events->add($event);
    }

    public function getEvents(): NestedEventCollection
    {
        return $this->events;
    }
}
