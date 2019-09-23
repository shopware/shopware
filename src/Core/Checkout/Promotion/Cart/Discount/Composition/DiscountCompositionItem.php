<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Promotion\Cart\Discount\Composition;

class DiscountCompositionItem
{
    /**
     * @var string
     */
    private $id;

    /**
     * @var int
     */
    private $quantity;

    /**
     * @var float
     */
    private $discountValue;

    public function __construct(string $id, int $quantity, float $discountValue)
    {
        $this->id = $id;
        $this->quantity = $quantity;
        $this->discountValue = $discountValue;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getQuantity(): int
    {
        return $this->quantity;
    }

    public function getDiscountValue(): float
    {
        return $this->discountValue;
    }
}
