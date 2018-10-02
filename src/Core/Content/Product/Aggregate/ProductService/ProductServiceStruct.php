<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Aggregate\ProductService;

use Shopware\Core\Checkout\Cart\Price\Struct\QuantityPriceDefinition;
use Shopware\Core\Checkout\Cart\Tax\Struct\PercentageTaxRule;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Content\Configuration\Aggregate\ConfigurationGroupOption\ConfigurationGroupOptionStruct;
use Shopware\Core\Content\Product\ProductStruct;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\ORM\Entity;
use Shopware\Core\Framework\Pricing\PriceRuleCollection;
use Shopware\Core\Framework\Pricing\PriceRuleStruct;
use Shopware\Core\Framework\Pricing\PriceStruct;
use Shopware\Core\System\Tax\TaxStruct;

class ProductServiceStruct extends Entity
{
    /**
     * @var string
     */
    protected $productId;

    /**
     * @var string
     */
    protected $optionId;

    /**
     * @var string
     */
    protected $taxId;

    /**
     * @var PriceStruct|null
     */
    protected $price;

    /**
     * @var ConfigurationGroupOptionStruct
     */
    protected $option;

    /**
     * @var TaxStruct
     */
    protected $tax;

    /**
     * @var PriceRuleCollection|null
     */
    protected $prices;

    /**
     * @var ProductStruct|null
     */
    protected $product;

    /**
     * @var \DateTime
     */
    protected $createdAt;

    /**
     * @var \DateTime|null
     */
    protected $updatedAt;

    public function getCreatedAt(): \DateTime
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTime $createdAt): void
    {
        $this->createdAt = $createdAt;
    }

    public function getUpdatedAt(): ?\DateTime
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTime $updatedAt): void
    {
        $this->updatedAt = $updatedAt;
    }

    public function getProductId(): string
    {
        return $this->productId;
    }

    public function setProductId(string $productId): void
    {
        $this->productId = $productId;
    }

    public function getOptionId(): string
    {
        return $this->optionId;
    }

    public function setOptionId(string $optionId): void
    {
        $this->optionId = $optionId;
    }

    public function getTaxId(): string
    {
        return $this->taxId;
    }

    public function setTaxId(string $taxId): void
    {
        $this->taxId = $taxId;
    }

    public function getPrice(): ?PriceStruct
    {
        return $this->price;
    }

    public function setPrice(PriceStruct $price): void
    {
        $this->price = $price;
    }

    public function getOption(): ConfigurationGroupOptionStruct
    {
        return $this->option;
    }

    public function setOption(
        ConfigurationGroupOptionStruct $option): void
    {
        $this->option = $option;
    }

    public function getTax(): TaxStruct
    {
        return $this->tax;
    }

    public function setTax(TaxStruct $tax): void
    {
        $this->tax = $tax;
    }

    public function getPriceDefinition(int $quantity, Context $context): QuantityPriceDefinition
    {
        $taxRules = $this->getTaxRuleCollection();

        $prices = $this->getPrices()->getPriceRulesForContext($context);

        if ($prices && $prices->count() > 0) {
            /** @var PriceRuleStruct $price */
            $price = $this->getPrices()->first();

            return new QuantityPriceDefinition($price->getPrice()->getGross(), $taxRules, $quantity, true);
        }

        if (!$this->getPrice()) {
            return new QuantityPriceDefinition(0, $taxRules, $quantity, true);
        }

        return new QuantityPriceDefinition($this->getPrice()->getGross(), $taxRules, $quantity, true);
    }

    public function getTaxRuleCollection(): TaxRuleCollection
    {
        return new TaxRuleCollection([
            new PercentageTaxRule($this->getTax()->getTaxRate(), 100),
        ]);
    }

    public function getPrices(): ?PriceRuleCollection
    {
        return $this->prices;
    }

    public function setPrices(PriceRuleCollection $prices): void
    {
        $this->prices = $prices;
    }

    public function getProduct(): ?ProductStruct
    {
        return $this->product;
    }

    public function setProduct(ProductStruct $product): void
    {
        $this->product = $product;
    }
}
