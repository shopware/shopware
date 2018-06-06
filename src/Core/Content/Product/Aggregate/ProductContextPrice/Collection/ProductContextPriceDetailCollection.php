<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Aggregate\ProductContextPrice\Collection;

use Shopware\Core\Content\Product\Aggregate\ProductContextPrice\Struct\ProductContextPriceDetailStruct;
use Shopware\Core\Content\Product\Collection\ProductBasicCollection;
use Shopware\Core\System\Currency\Collection\CurrencyBasicCollection;

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

    public function getRules(): \Shopware\Core\Content\Rule\Collection\RuleBasicCollection
    {
        return new \Shopware\Core\Content\Rule\Collection\RuleBasicCollection(
            $this->fmap(function (ProductContextPriceDetailStruct $productContextPrice) {
                return $productContextPrice->getRule();
            })
        );
    }

    protected function getExpectedClass(): string
    {
        return ProductContextPriceDetailStruct::class;
    }
}
