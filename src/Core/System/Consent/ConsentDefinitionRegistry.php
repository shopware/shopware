<?php declare(strict_types=1);

namespace Shopware\Core\System\Consent;

use Shopware\Core\Framework\Log\Package;
use Symfony\Contracts\Service\ResetInterface;

/**
 * @internal
 */
#[Package('data-services')]
class ConsentDefinitionRegistry implements ResetInterface
{
    /**
     * @var array<string, ConsentDefinition>|null
     */
    private ?array $consentDefinitions = null;

    /**
     * @param iterable<ConsentDefinition> $definitions
     * @param iterable<ConsentDefinitionProvider> $providers
     */
    public function __construct(
        private readonly iterable $definitions,
        private readonly iterable $providers,
    ) {
    }

    /**
     * @return array<string, ConsentDefinition>
     */
    public function all(): array
    {
        return $this->consentDefinitions ??= $this->collect();
    }

    public function get(string $name): ConsentDefinition
    {
        $definitions = $this->all();

        if (!isset($definitions[$name])) {
            throw ConsentException::notFound($name);
        }

        return $definitions[$name];
    }

    /**
     * Providers read installed apps, so the collected definitions change while the shop runs.
     */
    public function reset(): void
    {
        $this->consentDefinitions = null;
    }

    /**
     * @return array<string, ConsentDefinition>
     */
    private function collect(): array
    {
        $definitions = [];

        foreach ($this->providers as $provider) {
            foreach ($provider->getConsentDefinitions() as $definition) {
                $definitions[$definition->getName()] = $definition;
            }
        }

        // the tagged definitions are applied last, so a provider cannot replace one of them
        foreach ($this->definitions as $definition) {
            $definitions[$definition->getName()] = $definition;
        }

        return $definitions;
    }
}
