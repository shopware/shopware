<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Framework\Indexing;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;

/**
 * @deprecated tag:v6.3.0 - Use ElasticsearchIndexer instead
 */
class IndexMessageDispatcher
{
    public function dispatchForAllEntities(string $index, EntityDefinition $definition, Context $context): int
    {
        return 1;
    }

    public function dispatchForIds(array $ids, string $index, EntityDefinition $definition, Context $context): void
    {
    }
}
