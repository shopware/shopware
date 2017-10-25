<?php declare(strict_types=1);

namespace Shopware\Framework\Write;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\Event\NestedEventCollection;

abstract class EntityWrittenEvent extends NestedEvent
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
    private $rawData;

    /**
     * @var string[]
     */
    private $uuids = [];

    /**
     * @var string
     */
    private $entity;

    public function __construct(
        array $uuids,
        TranslationContext $context,
        array $rawData = [],
        array $errors = []
    ) {
        $this->events = new NestedEventCollection();
        $this->context = $context;
        $this->errors = $errors;
        $this->rawData = $rawData;
        $this->uuids = $uuids;
    }

    abstract public function getEntityName(): string;

    public function getName(): string
    {
        return 'entity.written';
    }

    public function getContext(): TranslationContext
    {
        return $this->context;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    public function getRawData(): array
    {
        return $this->rawData;
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
