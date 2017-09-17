<?php declare(strict_types=1);

namespace Shopware\Framework\Event;

class ConfigFormFieldTranslationWrittenEvent extends NestedEvent
{
    const NAME = 'config_form_field_translation.written';

    /**
     * @var string[]
     */
    private $configFormFieldTranslationUuids;

    /**
     * @var NestedEventCollection
     */
    private $events;

    /**
     * @var array
     */
    private $errors;

    public function __construct(array $configFormFieldTranslationUuids, array $errors = [])
    {
        $this->configFormFieldTranslationUuids = $configFormFieldTranslationUuids;
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
    public function getConfigFormFieldTranslationUuids(): array
    {
        return $this->configFormFieldTranslationUuids;
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
