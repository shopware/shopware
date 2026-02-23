<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Framework\Indexing;

use OpenSearch\Client;
use Shopware\Core\Framework\Log\Package;
use Shopware\Elasticsearch\Framework\ElasticsearchHelper;
use Shopware\Elasticsearch\Framework\ElasticsearchRegistry;

/**
 * @internal
 *
 * @codeCoverageIgnore - can not be unit tested; tested by it's usage in the ATS
 */
#[Package('framework')]
class IndexManager
{
    /**
     * @internal
     */
    public function __construct(
        private readonly Client $client,
        private readonly ElasticsearchHelper $helper,
        private readonly ElasticsearchRegistry $registry,
    ) {
    }

    /**
     * Opensearch only refreshes indices every second,
     * so we need to trigger a refresh after indexing to make sure the new data is available for search immediately.
     *
     * This is a performance heavy operation, so it should not be used in production settings, it's mainly intended for testing and development purposes.
     *
     * @see https://docs.opensearch.org/latest/api-reference/index-apis/refresh/
     */
    public function refreshIndices(): void
    {
        foreach ($this->registry->getDefinitions() as $definition) {
            $alias = $this->helper->getIndexName($definition->getEntityDefinition());

            try {
                $this->client->indices()->refresh(['index' => $alias]);
            } catch (\Exception) {
                // ignore refresh exceptions when the index does not exist,
                // it will be created on the next indexing run and does not need to be refreshed
            }
        }
    }
}
