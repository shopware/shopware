<?php declare(strict_types=1);

namespace Shopware\Category\Event;

use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\Event\NestedEventCollection;

class CategoryTranslationWrittenEvent extends NestedEvent
{
    const NAME = 'category_translation.written';

    /**
     * @var string[]
     */
    private $categoryTranslationUuids;

    /**
     * @var NestedEventCollection
     */
    private $events;

    /**
     * @var array
     */
    private $errors;

    public function __construct(array $categoryTranslationUuids, array $errors = [])
    {
        $this->categoryTranslationUuids = $categoryTranslationUuids;
        $this->events = new NestedEventCollection();
        $this->errors = $errors;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    /**
     * @return string[]
     */
    public function getCategoryTranslationUuids(): array
    {
        return $this->categoryTranslationUuids;
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
