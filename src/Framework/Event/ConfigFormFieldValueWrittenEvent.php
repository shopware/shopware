<?php declare(strict_types=1);

namespace Shopware\Framework\Event;

class ConfigFormFieldValueWrittenEvent extends NestedEvent
{
    const NAME = 'config_form_field_value.written';

    /**
     * @var string[]
     */
    private $configFormFieldValueUuids;

    /**
     * @var NestedEventCollection
     */
    private $events;

    /**
     * @var array
     */
    private $errors;

    public function __construct(array $configFormFieldValueUuids, array $errors = [])
    {
        $this->configFormFieldValueUuids = $configFormFieldValueUuids;
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
    public function getConfigFormFieldValueUuids(): array
    {
        return $this->configFormFieldValueUuids;
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
