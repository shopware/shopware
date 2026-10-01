<?php declare(strict_types=1);

namespace Shopware\Core\System\Consent;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('data-services')]
class ConsentDefinitionRegistry
{
    /**
     * @var array<string, ConsentDefinition>
     */
    private array $consentDefinitions;

    /**
     * @param iterable<ConsentDefinition> $consentDefinitions
     */
    public function __construct(iterable $consentDefinitions)
    {
        $definitions = [];
        foreach ($consentDefinitions as $definition) {
            $definitions[$definition->getName()] = $definition;
        }

        $this->consentDefinitions = $definitions;
    }

    /**
     * @return array<string, ConsentDefinition>
     */
    public function all(): array
    {
        return $this->consentDefinitions;
    }

    public function get(string $name): ConsentDefinition
    {
        if (!isset($this->consentDefinitions[$name])) {
            throw ConsentException::notFound($name);
        }

        return $this->consentDefinitions[$name];
    }
}
