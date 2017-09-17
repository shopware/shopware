<?php declare(strict_types=1);

namespace Shopware\CustomerAddress\Event;

use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\Event\NestedEventCollection;

class CustomerAddressWrittenEvent extends NestedEvent
{
    const NAME = 'customer_address.written';

    /**
     * @var string[]
     */
    private $customerAddressUuids;

    /**
     * @var NestedEventCollection
     */
    private $events;

    /**
     * @var array
     */
    private $errors;

    public function __construct(array $customerAddressUuids, array $errors = [])
    {
        $this->customerAddressUuids = $customerAddressUuids;
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
    public function getCustomerAddressUuids(): array
    {
        return $this->customerAddressUuids;
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
