<?php declare(strict_types=1);

namespace Shopware\Api\Entity\Write;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\Event\NestedEventCollection;

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
     * @var TranslationContext
     */
    protected $context;

    /**
     * @var array
     */
    protected $uuids = [];

    public function __construct(
        array $uuids,
        TranslationContext $context,
        array $errors = []
    ) {
        $this->events = new NestedEventCollection();
        $this->context = $context;
        $this->errors = $errors;
        $this->uuids = $uuids;
    }

    abstract public function getDefinition(): string;

    public function getContext(): TranslationContext
    {
        return $this->context;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    public function getUuids(): array
    {
        return $this->uuids;
    }

    public function hasErrors(): bool
    {
        return count($this->errors) > 0;
    }

    public function addEvent(NestedEvent $event): void
    {
        $this->events->add($event);
    }
}
