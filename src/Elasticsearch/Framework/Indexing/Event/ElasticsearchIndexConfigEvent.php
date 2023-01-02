<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Framework\Indexing\Event;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\ShopwareEvent;
use Shopware\Elasticsearch\Framework\AbstractElasticsearchDefinition;

/**
 * @package core
 */
#[Package('core')]
class ElasticsearchIndexConfigEvent implements ShopwareEvent
{
    private string $indexName;

    /**
     * @var array<mixed>
     */
    private array $config;

    private AbstractElasticsearchDefinition $definition;

    private Context $context;

    /**
     * @param array<mixed> $config
     */
    public function __construct(string $indexName, array $config, AbstractElasticsearchDefinition $definition, Context $context)
    {
        $this->indexName = $indexName;
        $this->config = $config;
        $this->definition = $definition;
        $this->context = $context;
    }

    public function getIndexName(): string
    {
        return $this->indexName;
    }

    /**
     * @return array<mixed>
     */
    public function getConfig(): array
    {
        return $this->config;
    }

    public function getDefinition(): AbstractElasticsearchDefinition
    {
        return $this->definition;
    }

    /**
     * @param array<mixed> $config
     */
    public function setConfig(array $config): void
    {
        $this->config = $config;
    }

    public function getContext(): Context
    {
        return $this->context;
    }
}
