<?php declare(strict_types=1);

namespace Shopware\Currency\Event;

use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\Event\NestedEventCollection;

class CurrencyTranslationWrittenEvent extends NestedEvent
{
    const NAME = 'currency_translation.written';

    /**
     * @var string[]
     */
    private $currencyTranslationUuids;

    /**
     * @var NestedEventCollection
     */
    private $events;

    /**
     * @var array
     */
    private $errors;

    public function __construct(array $currencyTranslationUuids, array $errors = [])
    {
        $this->currencyTranslationUuids = $currencyTranslationUuids;
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
    public function getCurrencyTranslationUuids(): array
    {
        return $this->currencyTranslationUuids;
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
