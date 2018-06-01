<?php declare(strict_types=1);

namespace Shopware\Framework\ORM\Write;

use Shopware\Framework\Context;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\Event\NestedEventCollection;
use Shopware\Framework\ORM\EntityDefinition;

abstract class WrittenEvent extends NestedEvent
{
    /**
     * @var NestedEventCollection
     */
    protected $events;

    /**
     * @var array
     */
    protected $errors;

    /**
     * @var Context
     */
    protected $context;

    /**
     * @var array
     */
    protected $ids;

    /**
     * @var array
     */
    protected $payload;

    /**
     * @var EntityExistence[]
     */
    protected $existences;

    public function __construct(
        array $ids,
        array $payload,
        array $existences,
        Context $context,
        array $errors = []
    ) {
        $this->events = new NestedEventCollection();
        $this->context = $context;
        $this->errors = $errors;
        $this->ids = $ids;
        $this->payload = $payload;
        $this->existences = $existences;
    }

    /**
     * @return string|EntityDefinition
     */
    abstract public function getDefinition(): string;

    public function getContext(): Context
    {
        return $this->context;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    public function getIds(): array
    {
        return $this->ids;
    }

    public function hasErrors(): bool
    {
        return count($this->errors) > 0;
    }

    public function addEvent(NestedEvent $event): void
    {
        $this->events->add($event);
    }

    public function getPayload(): array
    {
        return $this->payload;
    }

    /**
     * @return EntityExistence[]
     */
    public function getExistences(): array
    {
        return $this->existences;
    }
}
