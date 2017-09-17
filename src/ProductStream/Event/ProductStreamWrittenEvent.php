<?php declare(strict_types=1);

namespace Shopware\ProductStream\Event;

use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\Event\NestedEventCollection;

class ProductStreamWrittenEvent extends NestedEvent
{
    const NAME = 'product_stream.written';

    /**
     * @var string[]
     */
    private $productStreamUuids;

    /**
     * @var NestedEventCollection
     */
    private $events;

    /**
     * @var array
     */
    private $errors;

    public function __construct(array $productStreamUuids, array $errors = [])
    {
        $this->productStreamUuids = $productStreamUuids;
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
    public function getProductStreamUuids(): array
    {
        return $this->productStreamUuids;
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
