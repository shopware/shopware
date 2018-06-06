<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Aggregate\ProductContextPrice\Struct;

use Shopware\Core\Content\Rule\Struct\RuleBasicStruct;
use Shopware\Core\Content\Product\Struct\ProductBasicStruct;
use Shopware\Core\System\Currency\Struct\CurrencyBasicStruct;

class ProductContextPriceDetailStruct extends ProductContextPriceBasicStruct
{
    /**
     * @var ProductBasicStruct
     */
    protected $product;

    /**
     * @var CurrencyBasicStruct
     */
    protected $currency;

    /**
     * @var RuleBasicStruct
     */
    protected $rule;

    public function getProduct(): ProductBasicStruct
    {
        return $this->product;
    }

    public function setProduct(ProductBasicStruct $product): void
    {
        $this->product = $product;
    }

    public function getCurrency(): CurrencyBasicStruct
    {
        return $this->currency;
    }

    public function setCurrency(CurrencyBasicStruct $currency): void
    {
        $this->currency = $currency;
    }

    public function getRule(): \Shopware\Core\Content\Rule\Struct\RuleBasicStruct
    {
        return $this->rule;
    }

    public function setRule(\Shopware\Core\Content\Rule\Struct\RuleBasicStruct $rule): void
    {
        $this->rule = $rule;
    }
}
