<?php declare(strict_types=1);

namespace Shopware\Core\System\Currency\Aggregate\CurrencyTranslation\Event;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\NestedEvent;
use Shopware\Core\Framework\Event\NestedEventCollection;
use Shopware\Core\System\Currency\Aggregate\CurrencyTranslation\Collection\CurrencyTranslationDetailCollection;
use Shopware\Core\System\Currency\Event\CurrencyBasicLoadedEvent;
use Shopware\Core\System\Language\Event\LanguageBasicLoadedEvent;

class CurrencyTranslationDetailLoadedEvent extends NestedEvent
{
    public const NAME = 'currency_translation.detail.loaded';

    /**
     * @var \Shopware\Core\Framework\Context
     */
    protected $context;

    /**
     * @var \Shopware\Core\System\Currency\Aggregate\CurrencyTranslation\Collection\CurrencyTranslationDetailCollection
     */
    protected $currencyTranslations;

    public function __construct(CurrencyTranslationDetailCollection $currencyTranslations, Context $context)
    {
        $this->context = $context;
        $this->currencyTranslations = $currencyTranslations;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): Context
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
            $events[] = new LanguageBasicLoadedEvent($this->currencyTranslations->getLanguages(), $this->context);
        }

        return new NestedEventCollection($events);
    }
}
