<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Cart\Error\_fixtures;

use Shopware\Core\Checkout\Cart\Error\Error;
use Shopware\Core\Framework\Log\Package;

/**
 * A concrete error carrying one private and one protected property of its own, the two kinds of state
 * {@see Error::__serialize()} has to keep.
 *
 * @internal
 */
#[Package('checkout')]
class SerializableTestError extends Error
{
    /**
     * @param array<string, mixed> $parameters
     */
    public function __construct(
        private readonly string $identifier,
        protected array $parameters,
    ) {
        parent::__construct('Serializable test error ' . $identifier, 42);
    }

    public function getId(): string
    {
        return $this->identifier;
    }

    public function getMessageKey(): string
    {
        return 'serializable-test-error';
    }

    public function getLevel(): int
    {
        return self::LEVEL_WARNING;
    }

    public function blockOrder(): bool
    {
        return true;
    }

    public function getParameters(): array
    {
        return $this->parameters;
    }
}
