<?php declare(strict_types=1);

namespace Shopware\ProductStream\Event;

use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\Event\NestedEventCollection;

class ProductStreamAssignmentWrittenEvent extends NestedEvent
{
    const NAME = 'product_stream_assignment.written';

    /**
     * @var string[]
     */
    private $productStreamAssignmentUuids;

    /**
     * @var NestedEventCollection
     */
    private $events;

    /**
     * @var array
     */
    private $errors;

    public function __construct(array $productStreamAssignmentUuids, array $errors = [])
    {
        $this->productStreamAssignmentUuids = $productStreamAssignmentUuids;
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
    public function getProductStreamAssignmentUuids(): array
    {
        return $this->productStreamAssignmentUuids;
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
