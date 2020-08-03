<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\LineItem;

use Shopware\Core\Checkout\Cart\Price\QuantityPriceCalculator;
use Shopware\Core\Checkout\Cart\Price\Struct\QuantityPriceDefinition;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class LineItemQuantitySplitter
{
    /**
     * @var QuantityPriceCalculator
     */
    private $quantityPriceCalculator;

    public function __construct(QuantityPriceCalculator $quantityPriceCalculator)
    {
        $this->quantityPriceCalculator = $quantityPriceCalculator;
    }

    /**
     * Gets a new line item with only the provided quantity amount
     * along a ready-to-use calculated price.
     *
     * @throws \Shopware\Core\Checkout\Cart\Exception\InvalidQuantityException
     * @throws \Shopware\Core\Checkout\Cart\Exception\LineItemNotStackableException
     */
    public function split(LineItem $item, int $quantity, SalesChannelContext $context): LineItem
    {
        // clone the original line item
        $tmpItem = LineItem::createFromLineItem($item);

        // use calculated item price
        $unitPrice = $tmpItem->getPrice()->getUnitPrice();

        $taxRules = $tmpItem->getPrice()->getTaxRules();

        // change the quantity to 1 single item
        $tmpItem->setQuantity($quantity);

        $quantityDefinition = new QuantityPriceDefinition(
            $unitPrice,
            $taxRules,
            $context->getContext()->getCurrencyPrecision(),
            $tmpItem->getQuantity(),
            true
        );

        $price = $this->quantityPriceCalculator->calculate(
            $quantityDefinition,
            $context
        );

        $tmpItem->setPrice($price);

        return $tmpItem;
    }
}
