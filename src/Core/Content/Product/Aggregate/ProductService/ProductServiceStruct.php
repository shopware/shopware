<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Aggregate\ProductService;

use Shopware\Core\Checkout\Cart\Price\Struct\PriceDefinition;
use Shopware\Core\Checkout\Cart\Tax\Struct\PercentageTaxRule;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Content\Configuration\Aggregate\ConfigurationGroupOption\ConfigurationGroupOptionStruct;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\ORM\Entity;
use Shopware\Core\Framework\Pricing\PriceRuleCollection;
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
     * @var PriceRuleCollection
     */
    protected $priceRules;

    /**
     * @var ConfigurationGroupOptionStruct
     */
    protected $option;

    /**
     * @var TaxStruct
     */
    protected $tax;

    public function __construct()
    {
        $this->priceRules = new PriceRuleCollection();
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

    public function setPrice(?PriceStruct $price): void
    {
        $this->price = $price;
    }

    public function getPriceRules(): PriceRuleCollection
    {
        return $this->priceRules;
    }

    public function setPriceRules(PriceRuleCollection $priceRules): void
    {
        $this->priceRules = $priceRules;
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

    public function getPriceDefinition(int $quantity, Context $context): PriceDefinition
    {
        $taxRules = $this->getTaxRuleCollection();

        $prices = $this->getPriceRules()->getPriceRulesForContext($context);

        if ($prices && $prices->count() > 0) {
            $price = $this->priceRules->first();

            return new PriceDefinition($price->getPrice()->getGross(), $taxRules, $quantity, true);
        }

        if (!$this->getPrice()) {
            return new PriceDefinition(0, $taxRules, $quantity, true);
        }

        return new PriceDefinition($this->getPrice()->getGross(), $taxRules, $quantity, true);
    }

    public function getTaxRuleCollection()
    {
        return new TaxRuleCollection([
            new PercentageTaxRule($this->getTax()->getRate(), 100),
        ]);
    }
}
