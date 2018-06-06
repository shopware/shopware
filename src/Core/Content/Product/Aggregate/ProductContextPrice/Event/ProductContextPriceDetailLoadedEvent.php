<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Aggregate\ProductContextPrice\Event;

use Shopware\Core\Framework\Context;
use Shopware\Core\Content\Rule\Event\RuleBasicLoadedEvent;
use Shopware\Core\Content\Product\Aggregate\ProductContextPrice\Collection\ProductContextPriceDetailCollection;
use Shopware\Core\Content\Product\Event\ProductBasicLoadedEvent;
use Shopware\Core\Framework\Event\NestedEvent;
use Shopware\Core\Framework\Event\NestedEventCollection;
use Shopware\Core\System\Currency\Event\CurrencyBasicLoadedEvent;

class ProductContextPriceDetailLoadedEvent extends NestedEvent
{
    public const NAME = 'product_context_price.detail.loaded';

    /**
     * @var \Shopware\Core\Framework\Context
     */
    protected $context;

    /**
     * @var \Shopware\Core\Content\Product\Aggregate\ProductContextPrice\Collection\ProductContextPriceDetailCollection
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
        if ($this->productContextPrices->getRules()->count() > 0) {
            $events[] = new RuleBasicLoadedEvent($this->productContextPrices->getRules(), $this->context);
        }

        return new NestedEventCollection($events);
    }
}
