<?php declare(strict_types=1);

namespace Shopware\Tax\Event\Tax;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\Event\NestedEventCollection;
use Shopware\Product\Event\Product\ProductBasicLoadedEvent;
use Shopware\Tax\Collection\TaxDetailCollection;
use Shopware\Tax\Event\TaxAreaRule\TaxAreaRuleBasicLoadedEvent;

class TaxDetailLoadedEvent extends NestedEvent
{
    const NAME = 'tax.detail.loaded';

    /**
     * @var TranslationContext
     */
    protected $context;

    /**
     * @var TaxDetailCollection
     */
    protected $taxes;

    public function __construct(TaxDetailCollection $taxes, TranslationContext $context)
    {
        $this->context = $context;
        $this->taxes = $taxes;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): TranslationContext
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
        if ($this->taxes->getProducts()->count() > 0) {
            $events[] = new ProductBasicLoadedEvent($this->taxes->getProducts(), $this->context);
        }
        if ($this->taxes->getAreaRules()->count() > 0) {
            $events[] = new TaxAreaRuleBasicLoadedEvent($this->taxes->getAreaRules(), $this->context);
        }

        return new NestedEventCollection($events);
    }
}
