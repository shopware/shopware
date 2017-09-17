<?php declare(strict_types=1);

namespace Shopware\Framework\Event;

class ConfigFormWrittenEvent extends NestedEvent
{
    const NAME = 'config_form.written';

    /**
     * @var string[]
     */
    private $configFormUuids;

    /**
     * @var NestedEventCollection
     */
    private $events;

    /**
     * @var array
     */
    private $errors;

    public function __construct(array $configFormUuids, array $errors = [])
    {
        $this->configFormUuids = $configFormUuids;
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
    public function getConfigFormUuids(): array
    {
        return $this->configFormUuids;
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
