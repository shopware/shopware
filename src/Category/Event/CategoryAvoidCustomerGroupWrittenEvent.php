<?php declare(strict_types=1);

namespace Shopware\Category\Event;

use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\Event\NestedEventCollection;

class CategoryAvoidCustomerGroupWrittenEvent extends NestedEvent
{
    const NAME = 'category_avoid_customer_group.written';

    /**
     * @var string[]
     */
    private $categoryAvoidCustomerGroupUuids;

    /**
     * @var NestedEventCollection
     */
    private $events;

    /**
     * @var array
     */
    private $errors;

    public function __construct(array $categoryAvoidCustomerGroupUuids, array $errors = [])
    {
        $this->categoryAvoidCustomerGroupUuids = $categoryAvoidCustomerGroupUuids;
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
    public function getCategoryAvoidCustomerGroupUuids(): array
    {
        return $this->categoryAvoidCustomerGroupUuids;
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
