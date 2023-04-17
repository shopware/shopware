<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Facade;

use Shopware\Core\Checkout\Cart\CartException;
use Shopware\Core\Checkout\Cart\Facade\Traits\PriceFactoryTrait;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\PriceCollection as CalculatedPriceCollection;
use Shopware\Core\Checkout\Cart\Price\Struct\QuantityPriceDefinition;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\Price;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\PriceCollection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * The PriceFacade is a wrapper around a price.
 *
 * @script-service cart_manipulation
 * @script-service product
 */
#[Package('checkout')]
class PriceFacade
{
    use PriceFactoryTrait;

    /**
     * @internal
     */
    public function __construct(
        protected Entity|LineItem $item,
        protected CalculatedPrice $price,
        protected ScriptPriceStubs $priceStubs,
        protected SalesChannelContext $context
    ) {
    }

    /**
     * @internal
     */
    public function getInner(): CalculatedPrice
    {
        return $this->price;
    }

    /**
     * `getTotal()` returns the total price for the line-item.
     *
     * @return float The total price as float.
     */
    public function getTotal(): float
    {
        return $this->price->getTotalPrice();
    }

    /**
     * `getUnit()` returns the unit price for the line-item.
     * This is equivalent to the total price of the line-item with the quantity 1.
     *
     * @return float The price per unit as float.
     */
    public function getUnit(): float
    {
        return $this->price->getUnitPrice();
    }

    /**
     * `getQuantity()` returns the quantity that was used to calculate the total price.
     *
     * @return int Returns the quantity.
     */
    public function getQuantity(): int
    {
        return $this->price->getQuantity();
    }

    /**
     * `getTaxes()` returns the calculated taxes of the price.
     *
     * @return CalculatedTaxCollection Returns the calculated taxes.
     */
    public function getTaxes(): CalculatedTaxCollection
    {
        return $this->price->getCalculatedTaxes();
    }

    /**
     * `getRules()` returns the tax rules that were used to calculate the price.
     *
     * @return TaxRuleCollection Returns the tax rules.
     */
    public function getRules(): TaxRuleCollection
    {
        return $this->price->getTaxRules();
    }

    /**
     * `change()` allows a price overwrite of the current price scope. The provided price will be recalculated
     * over the quantity price calculator to consider quantity, tax rule and cash rounding configurations.
     *
     * @example pricing-cases/product-pricing.twig 40 5 Overwrite prices with a static defined collection
     *
     * @param PriceCollection $price The provided price can be a fetched price from the database or generated over the `PriceFactory` statically
     */
    public function change(PriceCollection $price): void
    {
        $value = $this->getPriceForTaxState($price, $this->context);

        $definition = new QuantityPriceDefinition(
            $value,
            $this->price->getTaxRules(),
            $this->getQuantity()
        );

        $this->overwrite($definition);
    }

    /**
     * `plus()` allows a price addition of the current price scope. The provided price will be recalculated via the quantity price calculator.
     * The provided price is interpreted as a unit price and will be added to the current unit price. The total price
     * is calculated afterwards considering quantity, tax rule and cash rounding configurations.
     *
     * @example pricing-cases/product-pricing.twig 14 5 Plus a static defined price to the existing calculated price
     *
     * @param PriceCollection $price The provided price can be a fetched price from the database or generated over the `PriceFactory` statically
     */
    public function plus(PriceCollection $price): void
    {
        $value = $this->getPriceForTaxState($price, $this->context);

        $definition = new QuantityPriceDefinition(
            $this->price->getUnitPrice() + abs($value),
            $this->price->getTaxRules(),
            $this->getQuantity()
        );

        $this->overwrite($definition);
    }

    /**
     * `minus()` allows a price subtraction of the current price scope. The provided price will be recalculated via the quantity price calculator.
     * The provided price is interpreted as a unit price and will reduce to the current unit price. The total price
     * is calculated afterwards considering quantity, tax rule and cash rounding configurations.
     *
     * @example pricing-cases/product-pricing.twig 22 5 Minus a static defined price to the existing calculated price
     *
     * @param PriceCollection $price The provided price can be a fetched price from the database or generated over the `PriceFactory` statically
     */
    public function minus(PriceCollection $price): void
    {
        $value = $this->getPriceForTaxState($price, $this->context);

        $definition = new QuantityPriceDefinition(
            $this->price->getUnitPrice() - abs($value),
            $this->price->getTaxRules(),
            $this->getQuantity()
        );

        $this->overwrite($definition);
    }

    /**
     * `discount()` allows a percentage discount calculation of the current price scope. The provided value will be ensured to be negative via `abs(value) * -1`.
     * The provided discount is interpreted as a percentage value and will be applied to the unit price and the total price as well.
     *
     * @example pricing-cases/product-pricing.twig 30 1 Adds a 10% discount to the existing calculated price
     *
     * @param float $value The percentage value of the discount. The value will be ensured to be negative via `abs(value) * -1`.
     */
    public function discount(float $value): void
    {
        $definition = new QuantityPriceDefinition($this->price->getUnitPrice(), $this->price->getTaxRules());
        $definition->setIsCalculated(true);

        $unit = $this->priceStubs->calculateQuantity($definition, $this->context);

        $discount = $this->priceStubs->calculatePercentage(\abs($value), new CalculatedPriceCollection([$unit]), $this->context);

        $definition = new QuantityPriceDefinition(
            $this->price->getUnitPrice() - $discount->getUnitPrice(),
            $this->price->getTaxRules(),
            $this->getQuantity()
        );

        $this->overwrite($definition);
    }

    /**
     * `surcharge()` allows a percentage surcharge calculation of the current price scope. The provided value will be ensured to be negative via `abs(value)`.
     * The provided surcharge is interpreted as a percentage value and will be applied to the unit price and the total price as well.
     *
     * @example pricing-cases/product-pricing.twig 34 1 Adds a 10% surcharge to the existing calculated price
     *
     * @param float $value The percentage value of the surcharge. The value will be ensured to be negative via `abs(value)`.
     */
    public function surcharge(float $value): void
    {
        $definition = new QuantityPriceDefinition($this->price->getUnitPrice(), $this->price->getTaxRules());
        $definition->setIsCalculated(true);

        $unit = $this->priceStubs->calculateQuantity($definition, $this->context);

        $discount = $this->priceStubs->calculatePercentage(\abs($value), new CalculatedPriceCollection([$unit]), $this->context);

        $definition = new QuantityPriceDefinition(
            $this->price->getUnitPrice() + $discount->getUnitPrice(),
            $this->price->getTaxRules(),
            $this->getQuantity()
        );

        $this->overwrite($definition);
    }

    protected function getPriceForTaxState(PriceCollection $price, SalesChannelContext $context): float
    {
        $currency = $price->getCurrencyPrice($this->context->getCurrencyId());

        if (!$currency instanceof Price) {
            throw CartException::invalidPriceDefinition();
        }

        if ($context->getTaxState() === CartPrice::TAX_STATE_GROSS) {
            return $currency->getGross();
        }

        return $currency->getNet();
    }

    private function overwrite(QuantityPriceDefinition $definition): void
    {
        if ($this->item instanceof LineItem) {
            $this->item->markModifiedByApp();

            $this->item->setPriceDefinition($definition);
        }

        $new = $this->priceStubs->calculateQuantity($definition, $this->context);

        $this->price->overwrite(
            $new->getUnitPrice(),
            $new->getTotalPrice(),
            $new->getCalculatedTaxes(),
        );
    }
}
