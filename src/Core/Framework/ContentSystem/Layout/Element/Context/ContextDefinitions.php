<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Layout\Element\Context;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
final readonly class ContextDefinitions
{
    /**
     * @param array<string, ContextProvider> $providers Indexed by context key
     * @param array<string, ContextConsumer> $consumers Indexed by context key
     */
    public function __construct(
        private array $providers = [],
        private array $consumers = []
    ) {
    }

    /**
     * @return array<string, ContextProvider>
     */
    public function getAllProviders(): array
    {
        return $this->providers;
    }

    /**
     * @codeCoverageIgnore
     *
     * @return array<string, ContextConsumer>
     */
    public function getAllConsumers(): array
    {
        return $this->consumers;
    }

    /**
     * @param array<string, ContextProvider> $additionalProviders
     */
    public function withAddedProviders(array $additionalProviders): self
    {
        return new self(
            array_merge($this->providers, $additionalProviders),
            $this->consumers
        );
    }
}
