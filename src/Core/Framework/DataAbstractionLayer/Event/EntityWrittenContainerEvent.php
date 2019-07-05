<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Event;

use Shopware\Core\Framework\Context;
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

    public function getEventByDefinition(string $definition): ?EntityWrittenEvent
    {
        foreach ($this->events as $event) {
            if (!$event instanceof EntityWrittenEvent) {
                continue;
            }
            if ($event->getDefinition()->getClass() === $definition) {
                return $event;
            }
        }

        return null;
    }

    public static function createWithWrittenEvents(array $identifiers, Context $context, array $errors): self
    {
        $events = new NestedEventCollection();

        foreach ($identifiers as $entityWrittenResults) {
            if (count($entityWrittenResults) === 0) {
                continue;
            }

            //@todo@jp fix data format
            $definition = $entityWrittenResults[0]->getDefinition();

            $events->add(
                new EntityWrittenEvent(
                    $definition,
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

        foreach ($identifiers as $data) {
            if (count($data) === 0) {
                continue;
            }

            //@todo@jp fix data format
            $definition = $data[0]->getDefinition();

            $events->add(
                new EntityDeletedEvent(
                    $definition,
                    $data,
                    $context,
                    $errors
                )
            );
        }

        return new self($context, $events, $errors);
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    public function getWrittenDefinitions(): array
    {
        return $this->events->map(function (EntityWrittenEvent $event) {
            return $event->getDefinition();
        });
    }
}
