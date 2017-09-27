<?php declare(strict_types=1);

namespace Shopware\Framework\Event;

use Shopware\Context\Struct\TranslationContext;

class MultiEditFilterWrittenEvent extends NestedEvent
{
    const NAME = 'multi_edit_filter.written';

    /**
     * @var string[]
     */
    protected $multiEditFilterUuids;

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

    public function __construct(array $multiEditFilterUuids, TranslationContext $context, array $errors = [])
    {
        $this->multiEditFilterUuids = $multiEditFilterUuids;
        $this->events = new NestedEventCollection();
        $this->context = $context;
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

    /**
     * @return string[]
     */
    public function getMultiEditFilterUuids(): array
    {
        return $this->multiEditFilterUuids;
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
