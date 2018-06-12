<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ORM\Event;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\NestedEvent;
use Shopware\Core\Framework\Event\NestedEventCollection;
use Shopware\Core\Framework\ORM\EntityDefinition;
use Shopware\Core\Framework\ORM\Write\EntityExistence;

class EntityWrittenEvent extends NestedEvent
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

    /**
     * @var string
     */
    protected $definition;

    /**
     * @var string
     */
    protected $name;

    public function __construct(
        string $definition,
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
        $this->definition = $definition;

        /* @var string|EntityDefinition $definition */
        $this->name = $definition::getEntityName() . '.written';
    }

    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @return string|EntityDefinition
     */
    public function getDefinition(): string
    {
        return $this->definition;
    }

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
