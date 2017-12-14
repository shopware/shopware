<?php declare(strict_types=1);

namespace Shopware\Api\Locale\Event\Locale;

use Shopware\Api\Locale\Collection\LocaleDetailCollection;
use Shopware\Api\Locale\Event\LocaleTranslation\LocaleTranslationBasicLoadedEvent;
use Shopware\Api\Shop\Event\Shop\ShopBasicLoadedEvent;
use Shopware\Api\User\Event\User\UserBasicLoadedEvent;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\Event\NestedEventCollection;

class LocaleDetailLoadedEvent extends NestedEvent
{
    const NAME = 'locale.detail.loaded';

    /**
     * @var TranslationContext
     */
    protected $context;

    /**
     * @var LocaleDetailCollection
     */
    protected $locales;

    public function __construct(LocaleDetailCollection $locales, TranslationContext $context)
    {
        $this->context = $context;
        $this->locales = $locales;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): TranslationContext
    {
        return $this->context;
    }

    public function getLocales(): LocaleDetailCollection
    {
        return $this->locales;
    }

    public function getEvents(): ?NestedEventCollection
    {
        $events = [];
        if ($this->locales->getTranslations()->count() > 0) {
            $events[] = new LocaleTranslationBasicLoadedEvent($this->locales->getTranslations(), $this->context);
        }
        if ($this->locales->getShops()->count() > 0) {
            $events[] = new ShopBasicLoadedEvent($this->locales->getShops(), $this->context);
        }
        if ($this->locales->getUsers()->count() > 0) {
            $events[] = new UserBasicLoadedEvent($this->locales->getUsers(), $this->context);
        }

        return new NestedEventCollection($events);
    }
}
