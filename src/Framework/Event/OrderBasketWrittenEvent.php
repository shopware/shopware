<?php declare(strict_types=1);

namespace Shopware\Framework\Event;

class OrderBasketWrittenEvent extends NestedEvent
{
    const NAME = 'order_basket.written';

    /**
     * @var string[]
     */
    private $orderBasketUuids;

    /**
     * @var NestedEventCollection
     */
    private $events;

    /**
     * @var array
     */
    private $errors;

    public function __construct(array $orderBasketUuids, array $errors = [])
    {
        $this->orderBasketUuids = $orderBasketUuids;
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
    public function getOrderBasketUuids(): array
    {
        return $this->orderBasketUuids;
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
