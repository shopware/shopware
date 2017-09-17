<?php declare(strict_types=1);

namespace Shopware\Framework\Event;

class ShoppingWorldComponentFieldWrittenEvent extends NestedEvent
{
    const NAME = 'shopping_world_component_field.written';

    /**
     * @var string[]
     */
    private $shoppingWorldComponentFieldUuids;

    /**
     * @var NestedEventCollection
     */
    private $events;

    /**
     * @var array
     */
    private $errors;

    public function __construct(array $shoppingWorldComponentFieldUuids, array $errors = [])
    {
        $this->shoppingWorldComponentFieldUuids = $shoppingWorldComponentFieldUuids;
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
    public function getShoppingWorldComponentFieldUuids(): array
    {
        return $this->shoppingWorldComponentFieldUuids;
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
