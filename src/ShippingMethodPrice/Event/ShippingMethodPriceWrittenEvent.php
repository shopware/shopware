<?php declare(strict_types=1);

namespace Shopware\ShippingMethodPrice\Event;

use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\Event\NestedEventCollection;

class ShippingMethodPriceWrittenEvent extends NestedEvent
{
    const NAME = 'shipping_method_price.written';

    /**
     * @var string[]
     */
    private $shippingMethodPriceUuids;

    /**
     * @var NestedEventCollection
     */
    private $events;

    /**
     * @var array
     */
    private $errors;

    public function __construct(array $shippingMethodPriceUuids, array $errors = [])
    {
        $this->shippingMethodPriceUuids = $shippingMethodPriceUuids;
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
    public function getShippingMethodPriceUuids(): array
    {
        return $this->shippingMethodPriceUuids;
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
