<?php declare(strict_types=1);

namespace Shopware\Framework\Event;

class OrderBasketSignaturesWrittenEvent extends NestedEvent
{
    const NAME = 'order_basket_signatures.written';

    /**
     * @var string[]
     */
    private $orderBasketSignaturesUuids;

    /**
     * @var NestedEventCollection
     */
    private $events;

    /**
     * @var array
     */
    private $errors;

    public function __construct(array $orderBasketSignaturesUuids, array $errors = [])
    {
        $this->orderBasketSignaturesUuids = $orderBasketSignaturesUuids;
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
    public function getOrderBasketSignaturesUuids(): array
    {
        return $this->orderBasketSignaturesUuids;
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
