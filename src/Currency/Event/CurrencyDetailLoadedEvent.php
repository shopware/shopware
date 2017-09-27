<?php declare(strict_types=1);

namespace Shopware\Currency\Event;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Currency\Struct\CurrencyDetailCollection;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\Event\NestedEventCollection;
use Shopware\Shop\Event\ShopBasicLoadedEvent;

class CurrencyDetailLoadedEvent extends NestedEvent
{
    const NAME = 'currency.detail.loaded';

    /**
     * @var CurrencyDetailCollection
     */
    protected $currencies;

    /**
     * @var TranslationContext
     */
    protected $context;

    public function __construct(CurrencyDetailCollection $currencies, TranslationContext $context)
    {
        $this->currencies = $currencies;
        $this->context = $context;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getCurrencies(): CurrencyDetailCollection
    {
        return $this->currencies;
    }

    public function getContext(): TranslationContext
    {
        return $this->context;
    }

    public function getEvents(): ?NestedEventCollection
    {
        $events = [
            new CurrencyBasicLoadedEvent($this->currencies, $this->context),
        ];

        if ($this->currencies->getShops()->count() > 0) {
            $events[] = new ShopBasicLoadedEvent($this->currencies->getShops(), $this->context);
        }

        return new NestedEventCollection($events);
    }
}
