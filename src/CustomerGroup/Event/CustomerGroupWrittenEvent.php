<?php declare(strict_types=1);

namespace Shopware\CustomerGroup\Event;

use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\Event\NestedEventCollection;

class CustomerGroupWrittenEvent extends NestedEvent
{
    const NAME = 'customer_group.written';

    /**
     * @var string[]
     */
    private $customerGroupUuids;

    /**
     * @var NestedEventCollection
     */
    private $events;

    /**
     * @var array
     */
    private $errors;

    public function __construct(array $customerGroupUuids, array $errors = [])
    {
        $this->customerGroupUuids = $customerGroupUuids;
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
    public function getCustomerGroupUuids(): array
    {
        return $this->customerGroupUuids;
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
