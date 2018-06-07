<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Aggregate\ProductPriceRule\Collection;

use Shopware\Core\Content\Product\Aggregate\ProductPriceRule\Struct\ProductPriceRuleDetailStruct;
use Shopware\Core\Content\Product\Collection\ProductBasicCollection;
use Shopware\Core\System\Currency\Collection\CurrencyBasicCollection;

class ProductPriceRuleDetailCollection extends ProductPriceRuleBasicCollection
{
    /**
     * @var ProductPriceRuleDetailStruct[]
     */
    protected $elements = [];

    public function getProducts(): ProductBasicCollection
    {
        return new ProductBasicCollection(
            $this->fmap(function (ProductPriceRuleDetailStruct $productPriceRule) {
                return $productPriceRule->getProduct();
            })
        );
    }

    public function getCurrencies(): CurrencyBasicCollection
    {
        return new CurrencyBasicCollection(
            $this->fmap(function (ProductPriceRuleDetailStruct $productPriceRule) {
                return $productPriceRule->getCurrency();
            })
        );
    }

    public function getRules(): \Shopware\Core\Content\Rule\Collection\RuleBasicCollection
    {
        return new \Shopware\Core\Content\Rule\Collection\RuleBasicCollection(
            $this->fmap(function (ProductPriceRuleDetailStruct $productPriceRule) {
                return $productPriceRule->getRule();
            })
        );
    }

    protected function getExpectedClass(): string
    {
        return ProductPriceRuleDetailStruct::class;
    }
}
