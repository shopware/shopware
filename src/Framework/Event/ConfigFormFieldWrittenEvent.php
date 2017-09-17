<?php declare(strict_types=1);

namespace Shopware\Framework\Event;

class ConfigFormFieldWrittenEvent extends NestedEvent
{
    const NAME = 'config_form_field.written';

    /**
     * @var string[]
     */
    private $configFormFieldUuids;

    /**
     * @var NestedEventCollection
     */
    private $events;

    /**
     * @var array
     */
    private $errors;

    public function __construct(array $configFormFieldUuids, array $errors = [])
    {
        $this->configFormFieldUuids = $configFormFieldUuids;
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
    public function getConfigFormFieldUuids(): array
    {
        return $this->configFormFieldUuids;
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
