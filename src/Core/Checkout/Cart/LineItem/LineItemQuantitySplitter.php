<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\LineItem;

use Shopware\Core\Checkout\Cart\CartException;
use Shopware\Core\Checkout\Cart\Price\QuantityPriceCalculator;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\QuantityPriceDefinition;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

#[Package('checkout')]
class LineItemQuantitySplitter
{
    /**
     * @internal
     */
    public function __construct(private readonly QuantityPriceCalculator $quantityPriceCalculator)
    {
    }

    /**
     * Gets a new line item with only the provided quantity amount
     * along a ready-to-use calculated price.
     *
     * @throws CartException
     */
    public function split(LineItem $item, int $quantity, SalesChannelContext $context): LineItem
    {
        if ($item->getQuantity() === $quantity) {
            return clone $item;
        }

        // clone the original line item
        $tmpItem = clone $item;

        // use calculated item price
        /** @var CalculatedPrice $lineItemPrice */
        $lineItemPrice = $tmpItem->getPrice();

        $unitPrice = $lineItemPrice->getUnitPrice();

        $taxRules = $lineItemPrice->getTaxRules();

        // change the quantity to 1 single item
        $tmpItem->setQuantity($quantity);

        $definition = new QuantityPriceDefinition($unitPrice, $taxRules, $tmpItem->getQuantity());

        $price = $this->quantityPriceCalculator->calculate($definition, $context);

        $price->assign([
            'listPrice' => $lineItemPrice->getListPrice() ?? null,
        ]);

        $tmpItem->setPrice($price);

        return $tmpItem;
    }
}
