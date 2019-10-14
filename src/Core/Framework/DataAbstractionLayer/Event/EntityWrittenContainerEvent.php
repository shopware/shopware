<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Event;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Shopware\Core\Framework\Event\NestedEvent;
use Shopware\Core\Framework\Event\NestedEventCollection;

class EntityWrittenContainerEvent extends NestedEvent
{
    /**
     * @var Context
     */
    protected $context;

    /**
     * @var NestedEventCollection
     */
    private $events;

    /**
     * @var array
     */
    private $errors;

    public function __construct(Context $context, NestedEventCollection $events, array $errors)
    {
        $this->context = $context;
        $this->events = $events;
        $this->errors = $errors;
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    public function getEvents(): ?NestedEventCollection
    {
        return $this->events;
    }

    public function getEventByEntityName(string $entityName): ?EntityWrittenEvent
    {
        foreach ($this->events as $event) {
            if (!$event instanceof EntityWrittenEvent) {
                continue;
            }

            if ($event->getEntityName() === $entityName) {
                return $event;
            }
        }

        return null;
    }

    public static function createWithWrittenEvents(array $identifiers, Context $context, array $errors): self
    {
        $events = new NestedEventCollection();

        /** @var EntityWriteResult[] $entityWrittenResults */
        foreach ($identifiers as $entityWrittenResults) {
            if (count($entityWrittenResults) === 0) {
                continue;
            }

            $entityName = $entityWrittenResults[0]->getEntityName();

            $events->add(
                new EntityWrittenEvent(
                    $entityName,
                    $entityWrittenResults,
                    $context,
                    $errors
                )
            );
        }

        return new self($context, $events, $errors);
    }

    public static function createWithDeletedEvents(array $identifiers, Context $context, array $errors): self
    {
        $events = new NestedEventCollection();

        /** @var EntityWriteResult[] $data */
        foreach ($identifiers as $data) {
            if (count($data) === 0) {
                continue;
            }

            $entityName = $data[0]->getEntityName();

            $events->add(
                new EntityDeletedEvent(
                    $entityName,
                    $data,
                    $context,
                    $errors
                )
            );
        }

        return new self($context, $events, $errors);
    }

    public function addEvent(NestedEvent ...$events): void
    {
        foreach ($events as $event) {
            $this->events->add($event);
        }
    }

    public function getErrors(): array
    {
        return $this->errors;
    }
}
