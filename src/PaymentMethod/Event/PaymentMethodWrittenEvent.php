<?php declare(strict_types=1);

namespace Shopware\PaymentMethod\Event;

use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\Event\NestedEventCollection;

class PaymentMethodWrittenEvent extends NestedEvent
{
    const NAME = 'payment_method.written';

    /**
     * @var string[]
     */
    private $paymentMethodUuids;

    /**
     * @var NestedEventCollection
     */
    private $events;

    /**
     * @var array
     */
    private $errors;

    public function __construct(array $paymentMethodUuids, array $errors = [])
    {
        $this->paymentMethodUuids = $paymentMethodUuids;
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
    public function getPaymentMethodUuids(): array
    {
        return $this->paymentMethodUuids;
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