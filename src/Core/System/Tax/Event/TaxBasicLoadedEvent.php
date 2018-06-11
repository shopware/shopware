<?php declare(strict_types=1);

namespace Shopware\Core\System\Tax\Event;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\NestedEvent;
use Shopware\Core\System\Tax\Collection\TaxBasicCollection;

class TaxBasicLoadedEvent extends NestedEvent
{
    public const NAME = 'tax.basic.loaded';

    /**
     * @var Context
     */
    protected $context;

    /**
     * @var TaxBasicCollection
     */
    protected $taxes;

    public function __construct(TaxBasicCollection $taxes, Context $context)
    {
        $this->context = $context;
        $this->taxes = $taxes;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    public function getTaxes(): TaxBasicCollection
    {
        return $this->taxes;
    }
}
