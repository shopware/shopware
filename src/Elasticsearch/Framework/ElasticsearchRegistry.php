<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Framework;

class ElasticsearchRegistry
{
    /**
     * @var iterable
     */
    private $definitions;

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
}
