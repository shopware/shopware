<?php declare(strict_types=1);

namespace Shopware\OrderAddress\Event;

use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\Event\NestedEventCollection;

class OrderAddressWrittenEvent extends NestedEvent
{
    const NAME = 'order_address.written';

    /**
     * @var string[]
     */
    private $orderAddressUuids;

    /**
     * @var NestedEventCollection
     */
    private $events;

    /**
     * @var array
     */
    private $errors;

    public function __construct(array $orderAddressUuids, array $errors = [])
    {
        $this->orderAddressUuids = $orderAddressUuids;
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
    public function getOrderAddressUuids(): array
    {
        return $this->orderAddressUuids;
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