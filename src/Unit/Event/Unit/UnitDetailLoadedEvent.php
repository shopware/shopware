<?php declare(strict_types=1);

namespace Shopware\Unit\Event\Unit;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\Event\NestedEventCollection;
use Shopware\Product\Event\Product\ProductBasicLoadedEvent;
use Shopware\Unit\Collection\UnitDetailCollection;
use Shopware\Unit\Event\UnitTranslation\UnitTranslationBasicLoadedEvent;

class UnitDetailLoadedEvent extends NestedEvent
{
    const NAME = 'unit.detail.loaded';

    /**
     * @var TranslationContext
     */
    protected $context;

    /**
     * @var UnitDetailCollection
     */
    protected $units;

    public function __construct(UnitDetailCollection $units, TranslationContext $context)
    {
        $this->context = $context;
        $this->units = $units;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): TranslationContext
    {
        return $this->context;
    }

    public function getUnits(): UnitDetailCollection
    {
        return $this->units;
    }

    public function getEvents(): ?NestedEventCollection
    {
        $events = [];
        if ($this->units->getProducts()->count() > 0) {
            $events[] = new ProductBasicLoadedEvent($this->units->getProducts(), $this->context);
        }
        if ($this->units->getTranslations()->count() > 0) {
            $events[] = new UnitTranslationBasicLoadedEvent($this->units->getTranslations(), $this->context);
        }

        return new NestedEventCollection($events);
    }
}
