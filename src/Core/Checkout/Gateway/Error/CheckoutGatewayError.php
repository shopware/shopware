<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Gateway\Error;

use Shopware\Core\Checkout\Cart\Error\Error;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;

#[Package('checkout')]
class CheckoutGatewayError extends Error
{
    private const KEY = 'checkout-gateway-error';

    public function __construct(
        private readonly string $reason,
        private readonly int $level,
        private readonly bool $blockOrder,
    ) {
        parent::__construct($this->reason);
    }

    public function getId(): string
    {
        return Uuid::randomHex();
    }

    public function blockOrder(): bool
    {
        return $this->blockOrder;
    }

    public function getMessageKey(): string
    {
        return self::KEY;
    }

    public function getLevel(): int
    {
        return $this->level;
    }

    public function getParameters(): array
    {
        return ['reason' => $this->reason];
    }

    public function isPersistent(): bool
    {
        return false;
    }
}
