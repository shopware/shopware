<?php declare(strict_types=1);

namespace Shopware\Framework\Event;

class BillingTemplateWrittenEvent extends NestedEvent
{
    const NAME = 'billing_template.written';

    /**
     * @var string[]
     */
    private $billingTemplateUuids;

    /**
     * @var NestedEventCollection
     */
    private $events;

    /**
     * @var array
     */
    private $errors;

    public function __construct(array $billingTemplateUuids, array $errors = [])
    {
        $this->billingTemplateUuids = $billingTemplateUuids;
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
    public function getBillingTemplateUuids(): array
    {
        return $this->billingTemplateUuids;
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
