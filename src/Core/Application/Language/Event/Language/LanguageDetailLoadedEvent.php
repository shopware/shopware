<?php declare(strict_types=1);

namespace Shopware\Application\Language\Event\Language;

use Shopware\Application\Language\Collection\LanguageDetailCollection;
use Shopware\Application\Context\Struct\ApplicationContext;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\Event\NestedEventCollection;

class LanguageDetailLoadedEvent extends NestedEvent
{
    public const NAME = 'language.detail.loaded';

    /**
     * @var ApplicationContext
     */
    protected $context;

    /**
     * @var LanguageDetailCollection
     */
    protected $languages;

    public function __construct(LanguageDetailCollection $languages, ApplicationContext $context)
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
        if ($this->languages->getChildren()->count() > 0) {
            $events[] = new LanguageBasicLoadedEvent($this->languages->getChildren(), $this->context);
        }

        return new NestedEventCollection($events);
    }
}
