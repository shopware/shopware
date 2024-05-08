<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Promotion\Cart\Error;

use Shopware\Core\Checkout\Cart\Error\Error;
use Shopware\Core\Framework\Log\Package;

#[Package('buyers-experience')]
class PromotionsOnCartPriceZeroError extends Error
{
    private const KEY = 'promotions-on-cart-price-zero-error';

    public function __construct(protected array $promotions)
    {
        $this->message = sprintf('Promotions %s were excluded for cart because the price of the cart is zero.', implode(', ',$this->promotions));

        parent::__construct($this->message);
    }

    public function isPersistent(): bool
    {
        return false;
    }

    public function getId(): string
    {
        return self::KEY;
    }

    public function getLevel(): int
    {
        return self::LEVEL_NOTICE;
    }

    public function getMessageKey(): string
    {
        return self::KEY;
    }

    public function getPromotions(): string
    {
        return $this->promotions;
    }

    public function blockOrder(): bool
    {
        return false;
    }

    public function getParameters(): array
    {
        return [
            'promotions' => $this->promotions,
        ];
    }
}
