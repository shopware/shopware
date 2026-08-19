<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Price;

use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\SelectedPrice;
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
    public function getDecorated(): AbstractPriceSelector
    {
        throw new DecorationPatternException(self::class);
    }

    public function select(Price $price, SalesChannelContext $context): SelectedPrice
    {
        $displayGross = $context->getTaxState() === CartPrice::TAX_STATE_GROSS;

        if ($context->getCurrentCustomerGroup()->getPriceBasis() !== CustomerGroupEntity::PRICE_BASIS_NET) {
            return new SelectedPrice($displayGross ? $price->getGross() : $price->getNet(), isCalculated: true);
        }

        return new SelectedPrice($price->getNet(), isCalculated: !$displayGross);
    }
}
