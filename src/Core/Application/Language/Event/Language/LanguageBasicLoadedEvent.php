<?php declare(strict_types=1);

namespace Shopware\Application\Language\Event\Language;

use Shopware\Application\Language\Collection\LanguageBasicCollection;
use Shopware\System\Locale\Event\Locale\LocaleBasicLoadedEvent;
use Shopware\Application\Context\Struct\ApplicationContext;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\Event\NestedEventCollection;

class LanguageBasicLoadedEvent extends NestedEvent
{
    public const NAME = 'language.basic.loaded';

    /**
     * @var ApplicationContext
     */
    protected $context;

    /**
     * @var LanguageBasicCollection
     */
    protected $languages;

    public function __construct(LanguageBasicCollection $languages, ApplicationContext $context)
    {
        $this->context = $context;
        $this->languages = $languages;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): ApplicationContext
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
