<?php declare(strict_types=1);

namespace Shopware\Core\System\Unit\Event;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\NestedEvent;
use Shopware\Core\System\Unit\Collection\UnitBasicCollection;

class UnitBasicLoadedEvent extends NestedEvent
{
    public const NAME = 'unit.basic.loaded';

    /**
     * @var \Shopware\Core\Framework\Context
     */
    protected $context;

    /**
     * @var UnitBasicCollection
     */
    protected $units;

    public function __construct(UnitBasicCollection $units, Context $context)
    {
        $this->context = $context;
        $this->units = $units;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    public function getUnits(): UnitBasicCollection
    {
        return $this->units;
    }
}
