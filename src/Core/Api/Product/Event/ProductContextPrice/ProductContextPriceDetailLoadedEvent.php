<?php declare(strict_types=1);

namespace Shopware\Api\Product\Event\ProductContextPrice;

use Shopware\Api\Context\Event\ContextRule\ContextRuleBasicLoadedEvent;
use Shopware\Api\Currency\Event\Currency\CurrencyBasicLoadedEvent;
use Shopware\Api\Product\Collection\ProductContextPriceDetailCollection;
use Shopware\Api\Product\Event\Product\ProductBasicLoadedEvent;
use Shopware\Context\Struct\ApplicationContext;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\Event\NestedEventCollection;

class ProductContextPriceDetailLoadedEvent extends NestedEvent
{
    public const NAME = 'product_context_price.detail.loaded';

    /**
     * @var ApplicationContext
     */
    protected $context;

    /**
     * @var ProductContextPriceDetailCollection
     */
    protected $productContextPrices;

    public function __construct(ProductContextPriceDetailCollection $productContextPrices, ApplicationContext $context)
    {
        $this->context = $context;
        $this->productContextPrices = $productContextPrices;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): ApplicationContext
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
