<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Aggregate\ProductPriceRule\Event;

use Shopware\Core\Framework\Context;
use Shopware\Core\Content\Rule\Event\RuleBasicLoadedEvent;
use Shopware\Core\Content\Product\Aggregate\ProductPriceRule\Collection\ProductPriceRuleDetailCollection;
use Shopware\Core\Content\Product\Event\ProductBasicLoadedEvent;
use Shopware\Core\Framework\Event\NestedEvent;
use Shopware\Core\Framework\Event\NestedEventCollection;
use Shopware\Core\System\Currency\Event\CurrencyBasicLoadedEvent;

class ProductPriceRuleDetailLoadedEvent extends NestedEvent
{
    public const NAME = 'product_price_rule.detail.loaded';

    /**
     * @var Context
     */
    protected $context;

    /**
     * @var ProductPriceRuleDetailCollection
     */
    protected $productPriceRules;

    public function __construct(ProductPriceRuleDetailCollection $productPriceRules, Context $context)
    {
        $this->context = $context;
        $this->productPriceRules = $productPriceRules;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    public function getProductPriceRules(): ProductPriceRuleDetailCollection
    {
        return $this->productPriceRules;
    }

    public function getEvents(): ?NestedEventCollection
    {
        $events = [];
        if ($this->productPriceRules->getProducts()->count() > 0) {
            $events[] = new ProductBasicLoadedEvent($this->productPriceRules->getProducts(), $this->context);
        }
        if ($this->productPriceRules->getCurrencies()->count() > 0) {
            $events[] = new CurrencyBasicLoadedEvent($this->productPriceRules->getCurrencies(), $this->context);
        }
        if ($this->productPriceRules->getRules()->count() > 0) {
            $events[] = new RuleBasicLoadedEvent($this->productPriceRules->getRules(), $this->context);
        }

        return new NestedEventCollection($events);
    }
}
