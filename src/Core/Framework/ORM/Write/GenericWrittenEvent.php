<?php declare(strict_types=1);

namespace Shopware\Framework\ORM\Write;

use Shopware\Application\Context\Struct\ApplicationContext;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\Event\NestedEventCollection;
use Shopware\Framework\ORM\EntityDefinition;

class GenericWrittenEvent extends NestedEvent
{
    public const NAME = 'entity.written';

    /**
     * @var ApplicationContext
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

    public function __construct(ApplicationContext $context, NestedEventCollection $events, array $errors)
    {
        $this->context = $context;
        $this->events = $events;
        $this->errors = $errors;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): ApplicationContext
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

    public static function createWithWrittenEvents(array $identifiers, ApplicationContext $context, array $errors): self
    {
        $events = new NestedEventCollection();

        /** @var EntityDefinition $definition */
        foreach ($identifiers as $definition => $data) {
            $class = $definition::getWrittenEventClass();
            $events->add(
                new $class(
                    array_column($data, 'primaryKey'),
                    array_column($data, 'payload'),
                    $context,
                    $errors
                )
            );
        }

        return new self($context, $events, $errors);
    }

    public static function createWithDeletedEvents(array $identifiers, ApplicationContext $context, array $errors): self
    {
        $events = new NestedEventCollection();

        /** @var EntityDefinition $definition */
        foreach ($identifiers as $definition => $data) {
            $class = $definition::getDeletedEventClass();
            $events->add(
                new $class(
                    array_column($data, 'primaryKey'),
                    array_column($data, 'payload'),
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
