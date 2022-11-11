<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Framework;

/**
 * @package core
 */
class ElasticsearchRegistry
{
    /**
     * @var AbstractElasticsearchDefinition[]
     */
    private iterable $definitions;

    /**
     * @internal
     *
     * @param iterable<AbstractElasticsearchDefinition> $definitions
     */
    public function __construct(iterable $definitions)
    {
        $this->definitions = $definitions;
    }

    /**
     * @return AbstractElasticsearchDefinition[]
     */
    public function getDefinitions(): iterable
    {
        return $this->definitions;
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
