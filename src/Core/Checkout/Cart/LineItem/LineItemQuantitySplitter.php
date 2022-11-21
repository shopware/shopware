<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\LineItem;

use Shopware\Core\Checkout\Cart\Exception\InvalidQuantityException;
use Shopware\Core\Checkout\Cart\Exception\LineItemNotStackableException;
use Shopware\Core\Checkout\Cart\Price\QuantityPriceCalculator;
use Shopware\Core\Checkout\Cart\Price\Struct\QuantityPriceDefinition;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class LineItemQuantitySplitter
{
    /**
     * @var QuantityPriceCalculator
     */
    private $quantityPriceCalculator;

    /**
     * @internal
     */
    public function __construct(QuantityPriceCalculator $quantityPriceCalculator)
    {
        $this->quantityPriceCalculator = $quantityPriceCalculator;
    }

    /**
     * Gets a new line item with only the provided quantity amount
     * along a ready-to-use calculated price.
     *
     * @throws InvalidQuantityException
     * @throws LineItemNotStackableException
     */
    public function split(LineItem $item, int $quantity, SalesChannelContext $context): LineItem
    {
        if ($item->getQuantity() === $quantity) {
            return clone $item;
        }

        // clone the original line item
        $tmpItem = clone $item;

        // use calculated item price
        $unitPrice = $tmpItem->getPrice()->getUnitPrice();

        $taxRules = $tmpItem->getPrice()->getTaxRules();

        // change the quantity to 1 single item
        $tmpItem->setQuantity($quantity);

        $definition = new QuantityPriceDefinition($unitPrice, $taxRules, $tmpItem->getQuantity());

        $price = $this->quantityPriceCalculator->calculate($definition, $context);

        $tmpItem->setPrice($price);

        return $tmpItem;
    }
}
