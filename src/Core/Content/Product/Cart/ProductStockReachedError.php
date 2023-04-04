<?php
declare(strict_types=1);

namespace Shopware\Core\Content\Product\Cart;

use Shopware\Core\Checkout\Cart\Error\Error;
use Shopware\Core\Framework\Log\Package;

#[Package('inventory')]
class ProductStockReachedError extends Error
{
    /**
     * @var string
     *
     * @deprecated tag:v6.6.0 - Will become private, be natively typed and moved to constructor property promotion
     */
    protected $id;

    /**
     * @var string
     *
     * @deprecated tag:v6.6.0 - Will become private, be natively typed and moved to constructor property promotion
     */
    protected $name;

    /**
     * @var int
     *
     * @deprecated tag:v6.6.0 - Will become private, be natively typed and moved to constructor property promotion
     */
    protected $quantity;

    public function __construct(
        string $id,
        string $name,
        int $quantity,
        /** @deprecated tag:v6.6.0 - Will become private */
        protected bool $resolved = true
    ) {
        $this->id = $id;
        $this->name = $name;
        $this->quantity = $quantity;

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
