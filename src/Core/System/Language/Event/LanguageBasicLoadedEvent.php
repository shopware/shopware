<?php declare(strict_types=1);

namespace Shopware\System\Language\Event;

use Shopware\Framework\Context;
use Shopware\System\Language\Collection\LanguageBasicCollection;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\Event\NestedEventCollection;
use Shopware\System\Locale\Event\LocaleBasicLoadedEvent;

class LanguageBasicLoadedEvent extends NestedEvent
{
    public const NAME = 'language.basic.loaded';

    /**
     * @var \Shopware\Framework\Context
     */
    protected $context;

    /**
     * @var LanguageBasicCollection
     */
    protected $languages;

    public function __construct(LanguageBasicCollection $languages, Context $context)
    {
        $this->context = $context;
        $this->languages = $languages;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    public function getLanguages(): LanguageBasicCollection
    {
        return $this->languages;
    }

    public function getEvents(): ?NestedEventCollection
    {
        $events = [];
        if ($this->languages->getLocales()->count() > 0) {
            $events[] = new LocaleBasicLoadedEvent($this->languages->getLocales(), $this->context);
        }

        return new NestedEventCollection($events);
    }
}
