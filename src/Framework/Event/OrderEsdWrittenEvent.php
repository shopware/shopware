<?php declare(strict_types=1);

namespace Shopware\Framework\Event;

class OrderEsdWrittenEvent extends NestedEvent
{
    const NAME = 'order_esd.written';

    /**
     * @var string[]
     */
    private $orderEsdUuids;

    /**
     * @var NestedEventCollection
     */
    private $events;

    /**
     * @var array
     */
    private $errors;

    public function __construct(array $orderEsdUuids, array $errors = [])
    {
        $this->orderEsdUuids = $orderEsdUuids;
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
    public function getOrderEsdUuids(): array
    {
        return $this->orderEsdUuids;
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
