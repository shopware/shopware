<?php declare(strict_types=1);

namespace Shopware\CustomerGroup\Event;

use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\Event\NestedEventCollection;

class CustomerGroupTranslationWrittenEvent extends NestedEvent
{
    const NAME = 'customer_group_translation.written';

    /**
     * @var string[]
     */
    private $customerGroupTranslationUuids;

    /**
     * @var NestedEventCollection
     */
    private $events;

    /**
     * @var array
     */
    private $errors;

    public function __construct(array $customerGroupTranslationUuids, array $errors = [])
    {
        $this->customerGroupTranslationUuids = $customerGroupTranslationUuids;
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
    public function getCustomerGroupTranslationUuids(): array
    {
        return $this->customerGroupTranslationUuids;
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
