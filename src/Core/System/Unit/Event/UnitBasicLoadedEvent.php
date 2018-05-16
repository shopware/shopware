<?php declare(strict_types=1);

namespace Shopware\System\Unit\Event;

use Shopware\System\Unit\Collection\UnitBasicCollection;
use Shopware\Application\Context\Struct\ApplicationContext;
use Shopware\Framework\Event\NestedEvent;

class UnitBasicLoadedEvent extends NestedEvent
{
    public const NAME = 'unit.basic.loaded';

    /**
     * @var ApplicationContext
     */
    protected $context;

    /**
     * @var UnitBasicCollection
     */
    protected $units;

    public function __construct(UnitBasicCollection $units, ApplicationContext $context)
    {
        $this->context = $context;
        $this->units = $units;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): ApplicationContext
    {
        return $this->context;
    }

    public function getUnits(): UnitBasicCollection
    {
        return $this->units;
    }
}
