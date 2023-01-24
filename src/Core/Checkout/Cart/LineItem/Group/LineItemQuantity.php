<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\LineItem\Group;

/**
 * @package checkout
 */
class LineItemQuantity
{
    public function __construct(private readonly string $lineItemId, private int $quantity)
    {
    }

    /**
     * Gets the id of the corresponding line item
     */
    public function getLineItemId(): string
    {
        return $this->lineItemId;
    }

    /**
     * Gets the quantity for this configuration.
     */
    public function getQuantity(): int
    {
        return $this->quantity;
    }

    /**
     * Sets a new quantity for this configuration.
     */
    public function setQuantity(int $quantity): void
    {
        $this->quantity = $quantity;
    }
}
