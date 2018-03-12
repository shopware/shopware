<?php declare(strict_types=1);

namespace Shopware\Api\Language\Event\Language;

use Shopware\Api\Language\Collection\LanguageDetailCollection;
use Shopware\Api\Locale\Event\Locale\LocaleBasicLoadedEvent;
use Shopware\Context\Struct\ShopContext;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\Event\NestedEventCollection;

class LanguageDetailLoadedEvent extends NestedEvent
{
    public const NAME = 'language.detail.loaded';

    /**
     * @var ShopContext
     */
    protected $context;

    /**
     * @var LanguageDetailCollection
     */
    protected $languages;

    public function __construct(LanguageDetailCollection $languages, ShopContext $context)
    {
        $this->context = $context;
        $this->languages = $languages;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): ShopContext
    {
        return $this->context;
    }

    public function getLanguages(): LanguageDetailCollection
    {
        return $this->languages;
    }

    public function getEvents(): ?NestedEventCollection
    {
        $events = [];
        if ($this->languages->getParents()->count() > 0) {
            $events[] = new LanguageBasicLoadedEvent($this->languages->getParents(), $this->context);
        }
        if ($this->languages->getLocales()->count() > 0) {
            $events[] = new LocaleBasicLoadedEvent($this->languages->getLocales(), $this->context);
        }
        if ($this->languages->getChildren()->count() > 0) {
            $events[] = new LanguageBasicLoadedEvent($this->languages->getChildren(), $this->context);
        }

        return new NestedEventCollection($events);
    }
}
