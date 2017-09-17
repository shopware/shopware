<?php declare(strict_types=1);

namespace Shopware\Framework\Event;

class CorePaymentInstanceWrittenEvent extends NestedEvent
{
    const NAME = 'core_payment_instance.written';

    /**
     * @var string[]
     */
    private $corePaymentInstanceUuids;

    /**
     * @var NestedEventCollection
     */
    private $events;

    /**
     * @var array
     */
    private $errors;

    public function __construct(array $corePaymentInstanceUuids, array $errors = [])
    {
        $this->corePaymentInstanceUuids = $corePaymentInstanceUuids;
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
    public function getCorePaymentInstanceUuids(): array
    {
        return $this->corePaymentInstanceUuids;
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
