<?php declare(strict_types=1);

namespace Shopware\Api\Unit\Event\Unit;

use Shopware\Api\Unit\Collection\UnitBasicCollection;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\NestedEvent;

class UnitBasicLoadedEvent extends NestedEvent
{
    const NAME = 'unit.basic.loaded';

    /**
     * @var TranslationContext
     */
    protected $context;

    /**
     * @var UnitBasicCollection
     */
    protected $units;

    public function __construct(UnitBasicCollection $units, TranslationContext $context)
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

    public function getUnits(): UnitBasicCollection
    {
        return $this->units;
    }
}
