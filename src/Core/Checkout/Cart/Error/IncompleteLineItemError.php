<?php
declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Error;

class IncompleteLineItemError extends Error
{
    /**
     * @var string
     */
    private $key;

    /**
     * @var string
     */
    private $property;

    public function __construct(string $key, string $property)
    {
        $this->key = $key;
        $this->property = $property;
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
