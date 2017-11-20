<?php declare(strict_types=1);

namespace Shopware\Locale\Event\LocaleTranslation;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\Event\NestedEventCollection;
use Shopware\Locale\Collection\LocaleTranslationDetailCollection;
use Shopware\Locale\Event\Locale\LocaleBasicLoadedEvent;
use Shopware\Shop\Event\Shop\ShopBasicLoadedEvent;

class LocaleTranslationDetailLoadedEvent extends NestedEvent
{
    const NAME = 'locale_translation.detail.loaded';

    /**
     * @var TranslationContext
     */
    protected $context;

    /**
     * @var LocaleTranslationDetailCollection
     */
    protected $localeTranslations;

    public function __construct(LocaleTranslationDetailCollection $localeTranslations, TranslationContext $context)
    {
        $this->context = $context;
        $this->localeTranslations = $localeTranslations;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): TranslationContext
    {
        return $this->context;
    }

    public function getLocaleTranslations(): LocaleTranslationDetailCollection
    {
        return $this->localeTranslations;
    }

    public function getEvents(): ?NestedEventCollection
    {
        $events = [];
        if ($this->localeTranslations->getLocales()->count() > 0) {
            $events[] = new LocaleBasicLoadedEvent($this->localeTranslations->getLocales(), $this->context);
        }
        if ($this->localeTranslations->getLanguages()->count() > 0) {
            $events[] = new ShopBasicLoadedEvent($this->localeTranslations->getLanguages(), $this->context);
        }

        return new NestedEventCollection($events);
    }
}
