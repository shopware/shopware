<?php declare(strict_types=1);

namespace Shopware\Unit\Event;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\Event\NestedEventCollection;
use Shopware\Unit\Struct\UnitBasicCollection;

class UnitBasicLoadedEvent extends NestedEvent
{
    const NAME = 'unit.basic.loaded';

    /**
     * @var UnitBasicCollection
     */
    protected $units;

    /**
     * @var TranslationContext
     */
    protected $context;

    public function __construct(UnitBasicCollection $units, TranslationContext $context)
    {
        $this->units = $units;
        $this->context = $context;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getUnits(): UnitBasicCollection
    {
        return $this->units;
    }

    public function getContext(): TranslationContext
    {
        return $this->context;
    }

    public function getEvents(): ?NestedEventCollection
    {
        return new NestedEventCollection([
        ]);
    }
}
