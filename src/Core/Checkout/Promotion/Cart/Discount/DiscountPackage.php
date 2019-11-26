<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Promotion\Cart\Discount;

use Shopware\Core\Checkout\Cart\LineItem\Group\LineItemQuantityCollection;
use Shopware\Core\Checkout\Cart\LineItem\LineItemFlatCollection;
use Shopware\Core\Checkout\Cart\Price\Struct\PriceCollection;
use Shopware\Core\Checkout\Promotion\Exception\PriceNotFoundException;

class DiscountPackage
{
    /** @var LineItemQuantityCollection */
    private $metaItems;

    /**
     * @var LineItemFlatCollection
     */
    private $cartItems;

    public function __construct(LineItemQuantityCollection $items)
    {
        $this->metaItems = $items;
        $this->cartItems = new LineItemFlatCollection();
    }

    public function getMetaData(): LineItemQuantityCollection
    {
        return $this->metaItems;
    }

    public function getCartItems(): LineItemFlatCollection
    {
        return $this->cartItems;
    }

    public function setCartItems(LineItemFlatCollection $items): void
    {
        $this->cartItems = $items;
    }

    /**
     * Gets the overall total price of all cart items in this package.
     */
    public function getTotalPrice(): float
    {
        $price = 0;

        foreach ($this->cartItems as $item) {
            $price += $item->getPrice()->getTotalPrice();
        }

        return $price;
    }

    /**
     * Gets the price collection of all cart items in this package.
     *
     * @throws PriceNotFoundException
     */
    public function getAffectedPrices(): PriceCollection
    {
        $affectedPrices = new PriceCollection();

        foreach ($this->cartItems as $lineItem) {
            if ($lineItem->getPrice() === null) {
                throw new PriceNotFoundException($lineItem);
            }

            $affectedPrices->add($lineItem->getPrice());
        }

        return $affectedPrices;
    }
}
