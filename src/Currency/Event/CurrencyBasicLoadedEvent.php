<?php declare(strict_types=1);

namespace Shopware\Currency\Event;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Currency\Struct\CurrencyBasicCollection;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\Event\NestedEventCollection;

class CurrencyBasicLoadedEvent extends NestedEvent
{
    const NAME = 'currency.basic.loaded';

    /**
     * @var CurrencyBasicCollection
     */
    protected $currencies;

    /**
     * @var TranslationContext
     */
    protected $context;

    public function __construct(CurrencyBasicCollection $currencies, TranslationContext $context)
    {
        $this->currencies = $currencies;
        $this->context = $context;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getCurrencies(): CurrencyBasicCollection
    {
        return $this->currencies;
    }

    public function getContext(): TranslationContext
    {
        return $this->context;
    }

    public function getEvents(): ?NestedEventCollection
    {
        $events = [];

        return new NestedEventCollection($events);
    }
}
