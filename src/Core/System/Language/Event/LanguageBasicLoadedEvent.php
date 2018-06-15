<?php declare(strict_types=1);

namespace Shopware\Core\System\Language\Event;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\NestedEvent;
use Shopware\Core\Framework\Event\NestedEventCollection;
use Shopware\Core\System\Language\Collection\LanguageBasicCollection;
use Shopware\Core\System\Locale\Event\LocaleBasicLoadedEvent;

class LanguageBasicLoadedEvent extends NestedEvent
{
    public const NAME = 'language.basic.loaded';

    /**
     * @var \Shopware\Core\Framework\Context
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
