<?php
declare(strict_types=1);

namespace Shopware\Core\Content\Product\Cart;

use Shopware\Core\Checkout\Cart\Error\Error;
use Shopware\Core\Framework\Log\Package;

#[Package('inventory')]
class ProductOutOfStockError extends Error
{
    /**
     * @var string
     */
    protected $id;

    /**
     * @var string
     */
    protected $name;

    public function __construct(
        string $id,
        string $name
    ) {
        $this->id = $id;

        $this->message = sprintf('The product %s is no longer available', $name);

        parent::__construct($this->message);
        $this->name = $name;
    }

    public function getParameters(): array
    {
        return ['name' => $this->name];
    }

    public function getId(): string
    {
        return $this->getMessageKey() . $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getMessageKey(): string
    {
        return 'product-out-of-stock';
    }

    public function getLevel(): int
    {
        return self::LEVEL_ERROR;
    }

    public function blockOrder(): bool
    {
        return true;
    }
}
