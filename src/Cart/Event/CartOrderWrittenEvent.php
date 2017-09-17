<?php declare(strict_types=1);

namespace Shopware\Cart\Event;

use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\Event\NestedEventCollection;

class CartOrderWrittenEvent extends NestedEvent
{
    const NAME = 'cart_order.written';

    /**
     * @var string[]
     */
    private $cartOrderUuids;

    /**
     * @var NestedEventCollection
     */
    private $events;

    /**
     * @var array
     */
    private $errors;

    public function __construct(array $cartOrderUuids, array $errors = [])
    {
        $this->cartOrderUuids = $cartOrderUuids;
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
    public function getCartOrderUuids(): array
    {
        return $this->cartOrderUuids;
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
