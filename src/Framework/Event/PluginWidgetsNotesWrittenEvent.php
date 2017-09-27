<?php declare(strict_types=1);

namespace Shopware\Framework\Event;

use Shopware\Context\Struct\TranslationContext;

class PluginWidgetsNotesWrittenEvent extends NestedEvent
{
    const NAME = 'plugin_widgets_notes.written';

    /**
     * @var string[]
     */
    protected $pluginWidgetsNotesUuids;

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

    public function __construct(array $pluginWidgetsNotesUuids, TranslationContext $context, array $errors = [])
    {
        $this->pluginWidgetsNotesUuids = $pluginWidgetsNotesUuids;
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
    public function getPluginWidgetsNotesUuids(): array
    {
        return $this->pluginWidgetsNotesUuids;
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
