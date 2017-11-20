<?php declare(strict_types=1);

namespace Shopware\Currency\Event\Currency;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Currency\Collection\CurrencyDetailCollection;
use Shopware\Currency\Event\CurrencyTranslation\CurrencyTranslationBasicLoadedEvent;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\Event\NestedEventCollection;
use Shopware\Order\Event\Order\OrderBasicLoadedEvent;
use Shopware\Shop\Event\Shop\ShopBasicLoadedEvent;

class CurrencyDetailLoadedEvent extends NestedEvent
{
    const NAME = 'currency.detail.loaded';

    /**
     * @var TranslationContext
     */
    protected $context;

    /**
     * @var CurrencyDetailCollection
     */
    protected $currencies;

    public function __construct(CurrencyDetailCollection $currencies, TranslationContext $context)
    {
        $this->context = $context;
        $this->currencies = $currencies;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): TranslationContext
    {
        return $this->context;
    }

    public function getCurrencies(): CurrencyDetailCollection
    {
        return $this->currencies;
    }

    public function getEvents(): ?NestedEventCollection
    {
        $events = [];
        if ($this->currencies->getTranslations()->count() > 0) {
            $events[] = new CurrencyTranslationBasicLoadedEvent($this->currencies->getTranslations(), $this->context);
        }
        if ($this->currencies->getOrders()->count() > 0) {
            $events[] = new OrderBasicLoadedEvent($this->currencies->getOrders(), $this->context);
        }
        if ($this->currencies->getAllShops()->count() > 0) {
            $events[] = new ShopBasicLoadedEvent($this->currencies->getAllShops(), $this->context);
        }

        return new NestedEventCollection($events);
    }
}
