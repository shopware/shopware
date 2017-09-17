<?php declare(strict_types=1);

namespace Shopware\Framework\Event;

class FilterProductWrittenEvent extends NestedEvent
{
    const NAME = 'filter_product.written';

    /**
     * @var string[]
     */
    private $filterProductUuids;

    /**
     * @var NestedEventCollection
     */
    private $events;

    /**
     * @var array
     */
    private $errors;

    public function __construct(array $filterProductUuids, array $errors = [])
    {
        $this->filterProductUuids = $filterProductUuids;
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
    public function getFilterProductUuids(): array
    {
        return $this->filterProductUuids;
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
