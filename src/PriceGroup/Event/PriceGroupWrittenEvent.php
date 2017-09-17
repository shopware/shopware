<?php declare(strict_types=1);

namespace Shopware\PriceGroup\Event;

use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\Event\NestedEventCollection;

class PriceGroupWrittenEvent extends NestedEvent
{
    const NAME = 'price_group.written';

    /**
     * @var string[]
     */
    private $priceGroupUuids;

    /**
     * @var NestedEventCollection
     */
    private $events;

    /**
     * @var array
     */
    private $errors;

    public function __construct(array $priceGroupUuids, array $errors = [])
    {
        $this->priceGroupUuids = $priceGroupUuids;
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
    public function getPriceGroupUuids(): array
    {
        return $this->priceGroupUuids;
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
