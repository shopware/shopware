<?php declare(strict_types=1);

namespace Shopware\Content\Product\Aggregate\ProductService\Struct;

use Shopware\Application\Context\Collection\ContextPriceCollection;
use Shopware\Application\Context\Struct\ApplicationContext;
use Shopware\Checkout\Cart\Price\Struct\PriceDefinition;
use Shopware\Checkout\Cart\Tax\Struct\PercentageTaxRule;
use Shopware\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Content\Product\Struct\PriceStruct;
use Shopware\Framework\ORM\Entity;
use Shopware\System\Configuration\Aggregate\ConfigurationGroupOption\Struct\ConfigurationGroupOptionBasicStruct;
use Shopware\System\Tax\Struct\TaxBasicStruct;

class ProductServiceBasicStruct extends Entity
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
     * @var ContextPriceCollection
     */
    protected $contextPrices;

    /**
     * @var \Shopware\System\Configuration\Aggregate\ConfigurationGroupOption\Struct\ConfigurationGroupOptionBasicStruct
     */
    protected $option;

    /**
     * @var TaxBasicStruct
     */
    protected $tax;

    public function __construct()
    {
        $this->contextPrices = new ContextPriceCollection();
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

    public function getContextPrices(): ContextPriceCollection
    {
        return $this->contextPrices;
    }

    public function setContextPrices(ContextPriceCollection $contextPrices): void
    {
        $this->contextPrices = $contextPrices;
    }

    public function getOption(): ConfigurationGroupOptionBasicStruct
    {
        return $this->option;
    }

    public function setOption(
        \Shopware\System\Configuration\Aggregate\ConfigurationGroupOption\Struct\ConfigurationGroupOptionBasicStruct $option): void
    {
        $this->option = $option;
    }

    public function getTax(): TaxBasicStruct
    {
        return $this->tax;
    }

    public function setTax(TaxBasicStruct $tax): void
    {
        $this->tax = $tax;
    }

    public function getPriceDefinition(int $quantity, ApplicationContext $context): PriceDefinition
    {
        $taxRules = $this->getTaxRuleCollection();

        $prices = $this->getContextPrices()->getPriceRulesForContext($context);

        if ($prices && $prices->count() > 0) {
            $price = $this->contextPrices->first();

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
