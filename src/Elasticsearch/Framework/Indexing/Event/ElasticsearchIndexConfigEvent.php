<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Framework\Indexing\Event;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\ShopwareEvent;
use Shopware\Core\Framework\Log\Package;
use Shopware\Elasticsearch\Framework\AbstractElasticsearchDefinition;

#[Package('core')]
class ElasticsearchIndexConfigEvent implements ShopwareEvent
{
    /**
     * @param array<mixed> $config
     */
    public function __construct(
        private readonly string $indexName,
        private array $config,
        private readonly AbstractElasticsearchDefinition $definition,
        private readonly Context $context
    ) {
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
