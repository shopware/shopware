<?php
declare(strict_types=1);

namespace Shopware\Core\Content\Product\Cart;

use Shopware\Core\Checkout\Cart\Error\Error;
use Shopware\Core\Framework\Log\Package;

#[Package('inventory')]
class ProductStockReachedError extends Error
{
    public function __construct(
        private readonly string $id,
        private readonly string $name,
        private readonly int $quantity,
        private bool $resolved = true
    ) {
        $this->message = sprintf(
            'The product %s is only available %d times',
            $name,
            $quantity
        );

        parent::__construct($this->message);
    }

    public function getParameters(): array
    {
        return ['name' => $this->name, 'quantity' => $this->quantity];
    }

    public function getId(): string
    {
        return $this->getMessageKey() . $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getQuantity(): int
    {
        return $this->quantity;
    }

    public function getMessageKey(): string
    {
        return 'product-stock-reached';
    }

    public function getLevel(): int
    {
        return $this->resolved ? self::LEVEL_WARNING : self::LEVEL_ERROR;
    }

    public function blockOrder(): bool
    {
        return true;
    }

    public function isPersistent(): bool
    {
        return $this->resolved;
    }
}
