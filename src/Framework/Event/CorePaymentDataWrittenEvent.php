<?php declare(strict_types=1);

namespace Shopware\Framework\Event;

class CorePaymentDataWrittenEvent extends NestedEvent
{
    const NAME = 'core_payment_data.written';

    /**
     * @var string[]
     */
    private $corePaymentDataUuids;

    /**
     * @var NestedEventCollection
     */
    private $events;

    /**
     * @var array
     */
    private $errors;

    public function __construct(array $corePaymentDataUuids, array $errors = [])
    {
        $this->corePaymentDataUuids = $corePaymentDataUuids;
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
    public function getCorePaymentDataUuids(): array
    {
        return $this->corePaymentDataUuids;
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
