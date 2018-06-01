<?php declare(strict_types=1);

namespace Shopware\System\Unit\Aggregate\UnitTranslation\Event;

use Shopware\Framework\Context;
use Shopware\System\Language\Event\LanguageBasicLoadedEvent;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\Event\NestedEventCollection;
use Shopware\System\Unit\Aggregate\UnitTranslation\Collection\UnitTranslationDetailCollection;
use Shopware\System\Unit\Event\UnitBasicLoadedEvent;

class UnitTranslationDetailLoadedEvent extends NestedEvent
{
    public const NAME = 'unit_translation.detail.loaded';

    /**
     * @var \Shopware\Framework\Context
     */
    protected $context;

    /**
     * @var UnitTranslationDetailCollection
     */
    protected $unitTranslations;

    public function __construct(UnitTranslationDetailCollection $unitTranslations, Context $context)
    {
        $this->context = $context;
        $this->unitTranslations = $unitTranslations;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    public function getUnitTranslations(): UnitTranslationDetailCollection
    {
        return $this->unitTranslations;
    }

    public function getEvents(): ?NestedEventCollection
    {
        $events = [];
        if ($this->unitTranslations->getUnits()->count() > 0) {
            $events[] = new UnitBasicLoadedEvent($this->unitTranslations->getUnits(), $this->context);
        }
        if ($this->unitTranslations->getLanguages()->count() > 0) {
            $events[] = new LanguageBasicLoadedEvent($this->unitTranslations->getLanguages(), $this->context);
        }

        return new NestedEventCollection($events);
    }
}
