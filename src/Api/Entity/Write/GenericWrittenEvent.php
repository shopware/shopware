<?php declare(strict_types=1);

namespace Shopware\Api\Entity\Write;

use Shopware\Api\Entity\EntityDefinition;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\Event\NestedEventCollection;

class GenericWrittenEvent extends NestedEvent
{
    public const NAME = 'generic.entity.written';

    /**
     * @var TranslationContext
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

    public function __construct(TranslationContext $context, NestedEventCollection $events, array $errors)
    {
        $this->context = $context;
        $this->events = $events;
        $this->errors = $errors;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): TranslationContext
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

    public static function createFromWriterResult(array $identifiers, TranslationContext $context, array $errors)
    {
        $events = new NestedEventCollection();

        /** @var EntityDefinition $definition */
        foreach ($identifiers as $definition => $ids) {
            $class = $definition::getWrittenEventClass();
            $events->add(new $class($ids, $context, $errors));
        }

        return new self($context, $events, $errors);
    }

    public function getErrors(): array
    {
        return $this->errors;
    }
}
