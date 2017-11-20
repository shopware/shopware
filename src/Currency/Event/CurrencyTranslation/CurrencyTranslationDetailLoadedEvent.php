<?php declare(strict_types=1);

namespace Shopware\Currency\Event\CurrencyTranslation;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Currency\Collection\CurrencyTranslationDetailCollection;
use Shopware\Currency\Event\Currency\CurrencyBasicLoadedEvent;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\Event\NestedEventCollection;
use Shopware\Shop\Event\Shop\ShopBasicLoadedEvent;

class CurrencyTranslationDetailLoadedEvent extends NestedEvent
{
    const NAME = 'currency_translation.detail.loaded';

    /**
     * @var TranslationContext
     */
    protected $context;

    /**
     * @var CurrencyTranslationDetailCollection
     */
    protected $currencyTranslations;

    public function __construct(CurrencyTranslationDetailCollection $currencyTranslations, TranslationContext $context)
    {
        $this->context = $context;
        $this->currencyTranslations = $currencyTranslations;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): TranslationContext
    {
        return $this->context;
    }

    public function getCurrencyTranslations(): CurrencyTranslationDetailCollection
    {
        return $this->currencyTranslations;
    }

    public function getEvents(): ?NestedEventCollection
    {
        $events = [];
        if ($this->currencyTranslations->getCurrencies()->count() > 0) {
            $events[] = new CurrencyBasicLoadedEvent($this->currencyTranslations->getCurrencies(), $this->context);
        }
        if ($this->currencyTranslations->getLanguages()->count() > 0) {
            $events[] = new ShopBasicLoadedEvent($this->currencyTranslations->getLanguages(), $this->context);
        }

        return new NestedEventCollection($events);
    }
}
