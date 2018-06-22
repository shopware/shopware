<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Aggregate\ProductPriceRule;

use Shopware\Core\Content\Product\ProductStruct;
use Shopware\Core\Content\Rule\RuleStruct;
use Shopware\Core\Framework\Pricing\PriceRuleStruct;
use Shopware\Core\System\Currency\CurrencyStruct;

class ProductPriceRuleStruct extends PriceRuleStruct
{
    /**
     * @var string
     */
    protected $productId;

    /**
     * @var int
     */
    protected $quantityStart;

    /**
     * @var int|null
     */
    protected $quantityEnd;

    /**
     * @var ProductStruct|null
     */
    protected $product;

    /**
     * @var CurrencyStruct|null
     */
    protected $currency;

    /**
     * @var RuleStruct|null
     */
    protected $rule;

    public function getProduct(): ?ProductStruct
    {
        return $this->product;
    }

    public function setProduct(ProductStruct $product): void
    {
        $this->product = $product;
    }

    public function getCurrency(): ?CurrencyStruct
    {
        return $this->currency;
    }

    public function setCurrency(CurrencyStruct $currency): void
    {
        $this->currency = $currency;
    }

    public function getRule(): ?RuleStruct
    {
        return $this->rule;
    }

    public function setRule(RuleStruct $rule): void
    {
        $this->rule = $rule;
    }

    public function getQuantityStart(): int
    {
        return $this->quantityStart;
    }

    public function setQuantityStart(int $quantityStart): void
    {
        $this->quantityStart = $quantityStart;
    }

    public function getQuantityEnd(): ?int
    {
        return $this->quantityEnd;
    }

    public function setQuantityEnd(?int $quantityEnd): void
    {
        $this->quantityEnd = $quantityEnd;
    }

    public function getProductId(): string
    {
        return $this->productId;
    }

    public function setProductId(string $productId): void
    {
        $this->productId = $productId;
    }
}
