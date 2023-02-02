<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Framework\Indexing\Event;

use Shopware\Elasticsearch\Framework\AbstractElasticsearchDefinition;

class ElasticsearchIndexCreatedEvent
{
    private string $indexName;

    private AbstractElasticsearchDefinition $definition;

    public function __construct(string $indexName, AbstractElasticsearchDefinition $definition)
    {
        $this->indexName = $indexName;
        $this->definition = $definition;
    }

    public function getIndexName(): string
    {
        return $this->indexName;
    }

    public function getDefinition(): AbstractElasticsearchDefinition
    {
        return $this->definition;
    }
}
