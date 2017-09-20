<?php declare(strict_types=1);

namespace Shopware\PaymentMethod\Event;

use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\Event\NestedEventCollection;

class PaymentMethodCountryWrittenEvent extends NestedEvent
{
    const NAME = 'payment_method_country.written';

    /**
     * @var string[]
     */
    private $paymentMethodCountryUuids;

    /**
     * @var NestedEventCollection
     */
    private $events;

    /**
     * @var array
     */
    private $errors;

    public function __construct(array $paymentMethodCountryUuids, array $errors = [])
    {
        $this->paymentMethodCountryUuids = $paymentMethodCountryUuids;
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
    public function getPaymentMethodCountryUuids(): array
    {
        return $this->paymentMethodCountryUuids;
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