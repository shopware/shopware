<?php declare(strict_types=1);

namespace Shopware\Framework\Event;

class CoreLicensesWrittenEvent extends NestedEvent
{
    const NAME = 'core_licenses.written';

    /**
     * @var string[]
     */
    private $coreLicensesUuids;

    /**
     * @var NestedEventCollection
     */
    private $events;

    /**
     * @var array
     */
    private $errors;

    public function __construct(array $coreLicensesUuids, array $errors = [])
    {
        $this->coreLicensesUuids = $coreLicensesUuids;
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
    public function getCoreLicensesUuids(): array
    {
        return $this->coreLicensesUuids;
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
