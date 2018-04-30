<?php declare(strict_types=1);

namespace Shopware\Api\Unit\Event\UnitTranslation;

use Shopware\Api\Language\Event\Language\LanguageBasicLoadedEvent;
use Shopware\Api\Unit\Collection\UnitTranslationDetailCollection;
use Shopware\Api\Unit\Event\Unit\UnitBasicLoadedEvent;
use Shopware\Context\Struct\ApplicationContext;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\Event\NestedEventCollection;

class UnitTranslationDetailLoadedEvent extends NestedEvent
{
    public const NAME = 'unit_translation.detail.loaded';

    /**
     * @var ApplicationContext
     */
    protected $context;

    /**
     * @var UnitTranslationDetailCollection
     */
    protected $unitTranslations;

    public function __construct(UnitTranslationDetailCollection $unitTranslations, ApplicationContext $context)
    {
        $this->context = $context;
        $this->unitTranslations = $unitTranslations;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): ApplicationContext
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
