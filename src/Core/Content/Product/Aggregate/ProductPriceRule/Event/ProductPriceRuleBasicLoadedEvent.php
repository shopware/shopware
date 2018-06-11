<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Aggregate\ProductPriceRule\Event;

use Shopware\Core\Framework\Context;
use Shopware\Core\Content\Product\Aggregate\ProductPriceRule\Collection\ProductPriceRuleBasicCollection;
use Shopware\Core\Framework\Event\NestedEvent;

class ProductPriceRuleBasicLoadedEvent extends NestedEvent
{
    public const NAME = 'product_price_rule.basic.loaded';

    /**
     * @var \Shopware\Core\Framework\Context
     */
    protected $context;

    /**
     * @var ProductPriceRuleBasicCollection
     */
    protected $productPriceRules;

    public function __construct(ProductPriceRuleBasicCollection $productPriceRules, Context $context)
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

    public function getProductPriceRules(): ProductPriceRuleBasicCollection
    {
        return $this->productPriceRules;
    }
}
