<?php declare(strict_types=1);

namespace Shopware\Api\Product\Struct;

use Shopware\Api\Product\Struct\ProductBasicStruct;
use Shopware\Api\Currency\Struct\CurrencyBasicStruct;
use Shopware\Api\Context\Struct\ContextRuleBasicStruct;

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
     * @var ContextRuleBasicStruct
     */
    protected $contextRule;

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


    public function getContextRule(): ContextRuleBasicStruct
    {
        return $this->contextRule;
    }

    public function setContextRule(ContextRuleBasicStruct $contextRule): void
    {
        $this->contextRule = $contextRule;
    }

}