<?php declare(strict_types=1);

namespace Shopware\Api\Tax\Event\Tax;

use Shopware\Api\Product\Event\Product\ProductBasicLoadedEvent;
use Shopware\Api\Tax\Collection\TaxDetailCollection;
use Shopware\Api\Tax\Event\TaxAreaRule\TaxAreaRuleBasicLoadedEvent;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\Event\NestedEventCollection;

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
