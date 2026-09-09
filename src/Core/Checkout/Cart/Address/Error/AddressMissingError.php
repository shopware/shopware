<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Address\Error;

use Shopware\Core\Checkout\Cart\Error\Error;
use Shopware\Core\Framework\Log\Package;

#[Package('checkout')]
abstract class AddressMissingError extends Error
{
    public function getMessageKey(): string
    {
        return $this->getId();
    }

    public function getLevel(): int
    {
        return Error::LEVEL_ERROR;
    }

    public function blockOrder(): bool
    {
        return true;
    }

    public function isPersistent(): bool
    {
        return false;
    }

    public function getParameters(): array
    {
        return [];
    }
}
