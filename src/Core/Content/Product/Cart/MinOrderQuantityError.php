<?php
declare(strict_types=1);

namespace Shopware\Core\Content\Product\Cart;

use Shopware\Core\Checkout\Cart\Error\Error;
use Shopware\Core\Framework\Log\Package;

#[Package('inventory')]
class MinOrderQuantityError extends Error
{
    protected string $name;

    protected int $quantity;

    public function __construct(
        protected string $id,
        string $name,
        int $quantity
    ) {
        $this->message = sprintf(
            'The quantity of product %s did not meet the minimum order quantity threshold. The quantity has automatically been increased to %s',
            $name,
            $quantity
        );

        parent::__construct($this->message);
        $this->name = $name;
        $this->quantity = $quantity;
    }

    public function getParameters(): array
    {
        return ['name' => $this->name, 'quantity' => $this->quantity];
    }

    public function getId(): string
    {
        return $this->getMessageKey() . $this->id;
    }

    public function getMessageKey(): string
    {
        return 'min-order-quantity';
    }

    public function getLevel(): int
    {
        return self::LEVEL_WARNING;
    }

    public function blockOrder(): bool
    {
        return true;
    }
}
