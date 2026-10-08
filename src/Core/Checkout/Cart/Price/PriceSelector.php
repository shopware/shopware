<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Price;

use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\SelectedPrice;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\Cart\Tax\TaxCalculator;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerGroup\CustomerGroupEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\Price;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * @final Depend on the AbstractPriceSelector which is the definition of public API for this scope
 */
#[Package('checkout')]
class PriceSelector extends AbstractPriceSelector
{
    /**
     * @internal
     */
    public function __construct(private readonly TaxCalculator $taxCalculator)
    {
    }

    public function getDecorated(): AbstractPriceSelector
    {
        throw new DecorationPatternException(self::class);
    }

    public function select(Price $price, TaxRuleCollection $taxRules, SalesChannelContext $context): SelectedPrice
    {
        $taxState = $context->getTaxState();

        return match ($context->getCurrentCustomerGroup()->getPriceBasis()) {
            CustomerGroupEntity::PRICE_BASIS_NET => new SelectedPrice(
                $price->getNet(),
                isCalculated: $taxState !== CartPrice::TAX_STATE_GROSS
            ),
            CustomerGroupEntity::PRICE_BASIS_GROSS => $this->selectFromGrossBasis($price, $taxRules, $taxState),
            default => new SelectedPrice(
                $taxState === CartPrice::TAX_STATE_GROSS ? $price->getGross() : $price->getNet(),
                isCalculated: true
            ),
        };
    }

    private function selectFromGrossBasis(Price $price, TaxRuleCollection $taxRules, string $taxState): SelectedPrice
    {
        if ($taxState === CartPrice::TAX_STATE_GROSS) {
            return new SelectedPrice($price->getGross(), isCalculated: true);
        }

        if ($taxState === CartPrice::TAX_STATE_FREE) {
            return new SelectedPrice($price->getNet(), isCalculated: true);
        }

        $containedTax = $this->taxCalculator
            ->calculateGrossTaxes($price->getGross(), $taxRules)
            ->getAmount();

        return new SelectedPrice($price->getGross() - $containedTax, isCalculated: true);
    }
}
