<?php declare(strict_types=1);

namespace Shopware\TaxAreaRule\Event;

use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\Event\NestedEventCollection;

class TaxAreaRuleTranslationWrittenEvent extends NestedEvent
{
    const NAME = 'tax_area_rule_translation.written';

    /**
     * @var string[]
     */
    private $taxAreaRuleTranslationUuids;

    /**
     * @var NestedEventCollection
     */
    private $events;

    /**
     * @var array
     */
    private $errors;

    public function __construct(array $taxAreaRuleTranslationUuids, array $errors = [])
    {
        $this->taxAreaRuleTranslationUuids = $taxAreaRuleTranslationUuids;
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
    public function getTaxAreaRuleTranslationUuids(): array
    {
        return $this->taxAreaRuleTranslationUuids;
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
