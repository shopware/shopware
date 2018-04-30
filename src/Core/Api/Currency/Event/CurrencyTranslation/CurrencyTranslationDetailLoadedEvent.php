<?php declare(strict_types=1);

namespace Shopware\Api\Currency\Event\CurrencyTranslation;

use Shopware\Api\Currency\Collection\CurrencyTranslationDetailCollection;
use Shopware\Api\Currency\Event\Currency\CurrencyBasicLoadedEvent;
use Shopware\Api\Language\Event\Language\LanguageBasicLoadedEvent;
use Shopware\Context\Struct\ApplicationContext;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\Event\NestedEventCollection;

class CurrencyTranslationDetailLoadedEvent extends NestedEvent
{
    public const NAME = 'currency_translation.detail.loaded';

    /**
     * @var ApplicationContext
     */
    protected $context;

    /**
     * @var CurrencyTranslationDetailCollection
     */
    protected $currencyTranslations;

    public function __construct(CurrencyTranslationDetailCollection $currencyTranslations, ApplicationContext $context)
    {
        $this->context = $context;
        $this->currencyTranslations = $currencyTranslations;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): ApplicationContext
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
