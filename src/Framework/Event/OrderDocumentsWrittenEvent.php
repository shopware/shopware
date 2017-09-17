<?php declare(strict_types=1);

namespace Shopware\Framework\Event;

class OrderDocumentsWrittenEvent extends NestedEvent
{
    const NAME = 'order_documents.written';

    /**
     * @var string[]
     */
    private $orderDocumentsUuids;

    /**
     * @var NestedEventCollection
     */
    private $events;

    /**
     * @var array
     */
    private $errors;

    public function __construct(array $orderDocumentsUuids, array $errors = [])
    {
        $this->orderDocumentsUuids = $orderDocumentsUuids;
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
    public function getOrderDocumentsUuids(): array
    {
        return $this->orderDocumentsUuids;
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
