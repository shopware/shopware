<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\LineItem\Group;

use Shopware\Core\Framework\Log\Package;

#[Package('checkout')]
class LineItemGroup
{
    /**
     * @var LineItemQuantity[]
     */
    private $items;

    /**
     * @internal
     */
    public function __construct()
    {
        $this->items = [];
    }

    /**
     * Adds a new data entry for the provided line item id.
     * It will increase the quantity if already existing.
     */
    public function addItem(string $lineItemId, int $quantity): void
    {
        if (!\array_key_exists($lineItemId, $this->items)) {
            $this->items[$lineItemId] = new LineItemQuantity($lineItemId, $quantity);
        } else {
            $package = $this->items[$lineItemId];
            $package->setQuantity($package->getQuantity() + $quantity);
        }
    }

    /**
     * Gets all added line item quantity packages
     * that this group contains.
     *
     * @return LineItemQuantity[]
     */
    public function getItems(): array
    {
        return array_values($this->items);
    }

    /**
     * Checks if items have been found for this group.
     */
    public function hasItems(): bool
    {
        return \count($this->items) > 0;
    }
}
