<?php
declare(strict_types=1);

namespace Shopware\Core\Content\Product\Cart;

use Shopware\Core\Checkout\Cart\Error\Error;

class MinOrderQuantityError extends Error
{
    /**
     * @var string
     */
    protected $id;

    /**
     * @var string
     */
    protected $name;

    /**
     * @var int
     */
    protected $quantity;

    public function __construct(string $id, string $name, int $quantity)
    {
        $this->id = $id;

        $this->message = sprintf(
            'Your input quantity does not meet the minimum order quantity of the %s. The quantity was changed to %s',
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
