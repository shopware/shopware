<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ORM\Write;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\NestedEvent;
use Shopware\Core\Framework\Event\NestedEventCollection;
use Shopware\Core\Framework\ORM\EntityDefinition;

class GenericWrittenEvent extends NestedEvent
{
    public const NAME = 'entity.written';

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

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    public function getEvents(): ?NestedEventCollection
    {
        return $this->events;
    }

    public function getEventByDefinition(string $definition): ?WrittenEvent
    {
        foreach ($this->events as $event) {
            if (!$event instanceof WrittenEvent) {
                continue;
            }
            if ($event->getDefinition() === $definition) {
                return $event;
            }
        }

        return null;
    }

    public static function createWithWrittenEvents(array $identifiers, Context $context, array $errors): self
    {
        $events = new NestedEventCollection();

        /** @var EntityDefinition $definition */
        foreach ($identifiers as $definition => $data) {
            $class = $definition::getWrittenEventClass();
            $events->add(
                new $class(
                    array_column($data, 'primaryKey'),
                    array_column($data, 'payload'),
                    array_column($data, 'existence'),
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

        /** @var EntityDefinition $definition */
        foreach ($identifiers as $definition => $data) {
            $class = $definition::getDeletedEventClass();
            $events->add(
                new $class(
                    array_column($data, 'primaryKey'),
                    array_column($data, 'payload'),
                    array_column($data, 'existence'),
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
}
