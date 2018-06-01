<?php declare(strict_types=1);

namespace Shopware\System\Locale\Aggregate\LocaleTranslation\Event;

use Shopware\Framework\Context;
use Shopware\System\Language\Event\LanguageBasicLoadedEvent;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\Event\NestedEventCollection;
use Shopware\System\Locale\Aggregate\LocaleTranslation\Collection\LocaleTranslationDetailCollection;
use Shopware\System\Locale\Event\LocaleBasicLoadedEvent;

class LocaleTranslationDetailLoadedEvent extends NestedEvent
{
    public const NAME = 'locale_translation.detail.loaded';

    /**
     * @var Context
     */
    protected $context;

    /**
     * @var \Shopware\System\Locale\Aggregate\LocaleTranslation\Collection\LocaleTranslationDetailCollection
     */
    protected $localeTranslations;

    public function __construct(LocaleTranslationDetailCollection $localeTranslations, Context $context)
    {
        $this->context = $context;
        $this->localeTranslations = $localeTranslations;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): Context
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
            $events[] = new LanguageBasicLoadedEvent($this->localeTranslations->getLanguages(), $this->context);
        }

        return new NestedEventCollection($events);
    }
}
