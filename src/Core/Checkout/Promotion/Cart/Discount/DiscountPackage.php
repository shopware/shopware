<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Promotion\Cart\Discount;

use Shopware\Core\Checkout\Cart\CartException;
use Shopware\Core\Checkout\Cart\LineItem\Group\LineItemQuantityCollection;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItem\LineItemFlatCollection;
use Shopware\Core\Checkout\Cart\Price\Struct\PriceCollection;
use Shopware\Core\Checkout\Promotion\Exception\PriceNotFoundException;
use Shopware\Core\Framework\Log\Package;

#[Package('checkout')]
class DiscountPackage
{
    private LineItemFlatCollection $cartItems;

    /**
     * @var array<string, LineItem>|null
     */
    private ?array $hashMap;

    public function __construct(private LineItemQuantityCollection $metaItems)
    {
        $this->cartItems = new LineItemFlatCollection();
        $this->hashMap = null;
    }

    public function getMetaData(): LineItemQuantityCollection
    {
        return $this->metaItems;
    }

    public function setMetaItems(LineItemQuantityCollection $metaItems): void
    {
        $this->metaItems = $metaItems;
    }

    public function getCartItems(): LineItemFlatCollection
    {
        return $this->cartItems;
    }

    public function getCartItem(string $id): LineItem
    {
        $map = $this->hasMap();

        if (isset($map[$id])) {
            return $map[$id];
        }

        throw CartException::lineItemNotFound($id);
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
            if ($item->getPrice() !== null) {
                $price += $item->getPrice()->getTotalPrice();
            }
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

    /**
     * @return array<string, LineItem>
     */
    private function hasMap(): array
    {
        if ($this->hashMap !== null) {
            return $this->hashMap;
        }

        $this->hashMap = [];
        foreach ($this->cartItems as $item) {
            // previous implementation always took the first element which maps the id
            // to prevent side effects, we keep this logic
            if (isset($this->hashMap[$item->getId()])) {
                continue;
            }
            $this->hashMap[$item->getId()] = $item;
        }

        return $this->hashMap;
    }
}
