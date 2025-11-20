<?php declare(strict_types=1);

namespace Shopware\Core\Content\ContentSystem\Layout\Element\Context;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('discovery')]
class ContextDefinitions
{
    /**
     * @param array<string, ContextProvider> $providers Indexed by context key
     * @param array<string, ContextConsumer> $consumers Indexed by context key
     */
    public function __construct(
        private readonly array $providers = [],
        private readonly array $consumers = []
    ) {
    }

    public static function empty(): self
    {
        return new self([], []);
    }

    public function provides(string $key): bool
    {
        return isset($this->providers[$key]);
    }

    public function accepts(string $key): bool
    {
        return isset($this->consumers[$key]);
    }

    /**
     * @return array<string, ContextProvider>
     */
    public function getAllProviders(): array
    {
        return $this->providers;
    }

    /**
     * @return array<string, ContextConsumer>
     */
    public function getAllConsumers(): array
    {
        return $this->consumers;
    }

    public function isEmpty(): bool
    {
        return $this->providers === [] && $this->consumers === [];
    }
}
