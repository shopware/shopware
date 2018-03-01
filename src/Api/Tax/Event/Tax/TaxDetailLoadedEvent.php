<?php declare(strict_types=1);

namespace Shopware\Api\Tax\Event\Tax;

use Shopware\Api\Product\Event\Product\ProductBasicLoadedEvent;
use Shopware\Api\Tax\Collection\TaxDetailCollection;
use Shopware\Api\Tax\Event\TaxAreaRule\TaxAreaRuleBasicLoadedEvent;
use Shopware\Context\Struct\ShopContext;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\Event\NestedEventCollection;

class TaxDetailLoadedEvent extends NestedEvent
{
    public const NAME = 'tax.detail.loaded';

    /**
     * @var ShopContext
     */
    protected $context;

    /**
     * @var TaxDetailCollection
     */
    protected $taxes;

    public function __construct(TaxDetailCollection $taxes, ShopContext $context)
    {
        $this->context = $context;
        $this->taxes = $taxes;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): ShopContext
    {
        return $this->context;
    }

    public function getTaxes(): TaxDetailCollection
    {
        return $this->taxes;
    }

    public function getEvents(): ?NestedEventCollection
    {
        $events = [];
        if ($this->taxes->getAreaRules()->count() > 0) {
            $events[] = new TaxAreaRuleBasicLoadedEvent($this->taxes->getAreaRules(), $this->context);
        }

        return new NestedEventCollection($events);
    }
}
