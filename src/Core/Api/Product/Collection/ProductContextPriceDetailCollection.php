<?php declare(strict_types=1);

namespace Shopware\Api\Product\Collection;

use Shopware\Api\Context\Collection\ContextRuleBasicCollection;
use Shopware\System\Currency\Collection\CurrencyBasicCollection;
use Shopware\Api\Product\Struct\ProductContextPriceDetailStruct;

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

    public function getContextRules(): ContextRuleBasicCollection
    {
        return new ContextRuleBasicCollection(
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
