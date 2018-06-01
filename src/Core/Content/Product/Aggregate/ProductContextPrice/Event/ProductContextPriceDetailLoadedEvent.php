<?php declare(strict_types=1);

namespace Shopware\Content\Product\Aggregate\ProductContextPrice\Event;

use Shopware\Framework\Context;
use Shopware\Checkout\Rule\Event\ContextRuleBasicLoadedEvent;
use Shopware\Content\Product\Aggregate\ProductContextPrice\Collection\ProductContextPriceDetailCollection;
use Shopware\Content\Product\Event\ProductBasicLoadedEvent;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\Event\NestedEventCollection;
use Shopware\System\Currency\Event\CurrencyBasicLoadedEvent;

class ProductContextPriceDetailLoadedEvent extends NestedEvent
{
    public const NAME = 'product_context_price.detail.loaded';

    /**
     * @var \Shopware\Framework\Context
     */
    protected $context;

    /**
     * @var \Shopware\Content\Product\Aggregate\ProductContextPrice\Collection\ProductContextPriceDetailCollection
     */
    protected $productContextPrices;

    public function __construct(ProductContextPriceDetailCollection $productContextPrices, Context $context)
    {
        $this->context = $context;
        $this->productContextPrices = $productContextPrices;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    public function getProductContextPrices(): ProductContextPriceDetailCollection
    {
        return $this->productContextPrices;
    }

    public function getEvents(): ?NestedEventCollection
    {
        $events = [];
        if ($this->productContextPrices->getProducts()->count() > 0) {
            $events[] = new ProductBasicLoadedEvent($this->productContextPrices->getProducts(), $this->context);
        }
        if ($this->productContextPrices->getCurrencies()->count() > 0) {
            $events[] = new CurrencyBasicLoadedEvent($this->productContextPrices->getCurrencies(), $this->context);
        }
        if ($this->productContextPrices->getContextRules()->count() > 0) {
            $events[] = new ContextRuleBasicLoadedEvent($this->productContextPrices->getContextRules(), $this->context);
        }

        return new NestedEventCollection($events);
    }
}
