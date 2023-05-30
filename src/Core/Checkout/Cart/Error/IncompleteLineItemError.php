<?php
declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Error;

use Shopware\Core\Framework\Log\Package;

#[Package('checkout')]
class IncompleteLineItemError extends Error
{
    public function __construct(
        private string $key,
        private readonly string $property
    ) {
        $this->message = sprintf('Line item "%s" incomplete. Property "%s" missing.', $key, $property);

        parent::__construct($this->message);
    }

    public function getParameters(): array
    {
        return ['key' => $this->key, 'property' => $this->property];
    }

    public function getId(): string
    {
        return $this->key;
    }

    public function getMessageKey(): string
    {
        return $this->property;
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
