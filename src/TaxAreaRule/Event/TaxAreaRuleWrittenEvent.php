<?php declare(strict_types=1);

namespace Shopware\TaxAreaRule\Event;

use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\Event\NestedEventCollection;

class TaxAreaRuleWrittenEvent extends NestedEvent
{
    const NAME = 'tax_area_rule.written';

    /**
     * @var string[]
     */
    private $taxAreaRuleUuids;

    /**
     * @var NestedEventCollection
     */
    private $events;

    /**
     * @var array
     */
    private $errors;

    public function __construct(array $taxAreaRuleUuids, array $errors = [])
    {
        $this->taxAreaRuleUuids = $taxAreaRuleUuids;
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
    public function getTaxAreaRuleUuids(): array
    {
        return $this->taxAreaRuleUuids;
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
