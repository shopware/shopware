<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Framework\Indexing;

use OpenSearch\Client;
use OpenSearch\Common\Exceptions\BadRequest400Exception;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Elasticsearch\Framework\ElasticsearchHelper;
use Shopware\Elasticsearch\Framework\ElasticsearchRegistry;
use Shopware\Elasticsearch\Product\ElasticsearchProductException;

#[Package('core')]
class IndexMappingUpdater
{
    /**
     * @internal
     */
    public function __construct(
        private readonly ElasticsearchRegistry $registry,
        private readonly ElasticsearchHelper $elasticsearchHelper,
        private readonly Client $client,
        private readonly IndexMappingProvider $indexMappingProvider
    ) {
    }

    public function update(Context $context): void
    {
        foreach ($this->registry->getDefinitions() as $definition) {
            $indexName = $this->elasticsearchHelper->getIndexName($definition->getEntityDefinition());

            try {
                $this->client->indices()->putMapping([
                    'index' => $indexName,
                    'body' => $this->indexMappingProvider->build($definition, $context),
                ]);
            } catch (BadRequest400Exception $exception) {
                if (str_contains($exception->getMessage(), 'cannot be changed from type')) {
                    throw ElasticsearchProductException::cannotChangeCustomFieldType($exception);
                }
            }
        }
    }
}
