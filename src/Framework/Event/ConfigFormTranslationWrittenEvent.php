<?php declare(strict_types=1);

namespace Shopware\Framework\Event;

class ConfigFormTranslationWrittenEvent extends NestedEvent
{
    const NAME = 'config_form_translation.written';

    /**
     * @var string[]
     */
    private $configFormTranslationUuids;

    /**
     * @var NestedEventCollection
     */
    private $events;

    /**
     * @var array
     */
    private $errors;

    public function __construct(array $configFormTranslationUuids, array $errors = [])
    {
        $this->configFormTranslationUuids = $configFormTranslationUuids;
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
    public function getConfigFormTranslationUuids(): array
    {
        return $this->configFormTranslationUuids;
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
