<?php declare(strict_types=1);

namespace Shopware\Content\Product\Aggregate\ProductContextPrice\Collection;

use Shopware\Content\Product\Aggregate\ProductContextPrice\Struct\ProductContextPriceDetailStruct;
use Shopware\Content\Product\Collection\ProductBasicCollection;
use Shopware\System\Currency\Collection\CurrencyBasicCollection;

class ProductContextPriceDetailCollection extends ProductContextPriceBasicCollection
{
    /**
     * @var ProductContextPriceDetailStruct[]
     */
    protected $elements = [];

    public function getProducts(): ProductBasicCollection
    {
        return new ProductBasicCollection(
            $this->fmap(function (ProductContextPriceDetailStruct $productContextPrice) {
                return $productContextPrice->getProduct();
            })
        );
    }

    public function getCurrencies(): CurrencyBasicCollection
    {
        return new CurrencyBasicCollection(
            $this->fmap(function (ProductContextPriceDetailStruct $productContextPrice) {
                return $productContextPrice->getCurrency();
            })
        );
    }

    public function getContextRules(): \Shopware\Checkout\Rule\Collection\ContextRuleBasicCollection
    {
        return new \Shopware\Checkout\Rule\Collection\ContextRuleBasicCollection(
            $this->fmap(function (ProductContextPriceDetailStruct $productContextPrice) {
                return $productContextPrice->getContextRule();
            })
        );
    }

    protected function getExpectedClass(): string
    {
        return ProductContextPriceDetailStruct::class;
    }
}
