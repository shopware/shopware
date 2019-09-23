<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\LineItem\Group;

class LineItemQuantity
{
    /**
     * @var string
     */
    private $lineItemId;

    /**
     * @var int
     */
    private $quantity;

    public function __construct(string $lineItemId, int $quantity)
    {
        $this->lineItemId = $lineItemId;
        $this->quantity = $quantity;
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
