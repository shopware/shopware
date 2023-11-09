<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Framework;

use Shopware\Core\Framework\Log\Package;

#[Package('core')]
class ElasticsearchRegistry
{
    /**
     * @internal
     *
     * @param AbstractElasticsearchDefinition[] $definitions
     */
    public function __construct(private readonly iterable $definitions)
    {
    }

    /**
     * @return AbstractElasticsearchDefinition[]
     */
    public function getDefinitions(): iterable
    {
        return $this->definitions;
    }

    /**
     * @return iterable<string>
     */
    public function getDefinitionNames(): iterable
    {
        $names = [];

        foreach ($this->getDefinitions() as $definition) {
            $names[] = $definition->getEntityDefinition()->getEntityName();
        }

        return $names;
    }

    public function get(string $entityName): ?AbstractElasticsearchDefinition
    {
        foreach ($this->getDefinitions() as $definition) {
            if ($definition->getEntityDefinition()->getEntityName() === $entityName) {
                return $definition;
            }
        }

        return null;
    }

    public function has(string $entityName): bool
    {
        foreach ($this->getDefinitions() as $definition) {
            if ($definition->getEntityDefinition()->getEntityName() === $entityName) {
                return true;
            }
        }

        return false;
    }
}
