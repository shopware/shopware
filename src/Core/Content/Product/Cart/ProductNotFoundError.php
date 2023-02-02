<?php
declare(strict_types=1);

namespace Shopware\Core\Content\Product\Cart;

use Shopware\Core\Checkout\Cart\Error\Error;

class ProductNotFoundError extends Error
{
    /**
     * @var string
     */
    protected $id;

    public function __construct(string $id)
    {
        $this->id = $id;

        parent::__construct('The product %s could not be found');
    }

    public function getParameters(): array
    {
        return ['id' => $this->id];
    }

    public function getId(): string
    {
        return $this->getMessageKey() . $this->id;
    }

    public function getMessageKey(): string
    {
        return 'product-not-found';
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
