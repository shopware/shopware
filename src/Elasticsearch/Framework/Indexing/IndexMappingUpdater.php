<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Framework\Indexing;

use OpenSearch\Client;
use OpenSearch\Exception\BadRequestHttpException;
use OpenSearch\Exception\NotFoundHttpException;
use Shopware\Core\Framework\Adapter\Storage\AbstractKeyValueStorage;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Elasticsearch\Framework\ElasticsearchHelper;
use Shopware\Elasticsearch\Framework\ElasticsearchRegistry;
use Shopware\Elasticsearch\Framework\SystemUpdateListener;
use Shopware\Elasticsearch\Product\ElasticsearchProductException;

#[Package('framework')]
class IndexMappingUpdater
{
    /**
     * putMapping errors that cannot be resolved on a live index; the affected entity has to
     * be reindexed into a freshly created index instead. This includes analysis-settings
     * mismatches ("has not been configured in mappings"), because analyzers/normalizers are
     * fixed at index creation and cannot be added to a live index.
     */
    private const REINDEXABLE_MAPPING_ERRORS = [
        'conflicts with existing mapper:\n\tCannot update parameter',
        'cannot be changed from type',
        'can\'t merge a non object mapping',
        'cannot change object mapping from',
        'has not been configured in mappings',
    ];

    /**
     * @internal
     */
    public function __construct(
        private readonly ElasticsearchRegistry $registry,
        private readonly ElasticsearchHelper $elasticsearchHelper,
        private readonly Client $client,
        private readonly IndexMappingProvider $indexMappingProvider,
        private readonly AbstractKeyValueStorage $storage
    ) {
    }

    public function update(Context $context): void
    {
        if (!$this->elasticsearchHelper->allowIndexing()) {
            return;
        }

        $entitiesToReindex = $this->storage->get(SystemUpdateListener::CONFIG_KEY, []) ?? [];

        if (\is_string($entitiesToReindex)) {
            $entitiesToReindex = \json_decode($entitiesToReindex, true);
        }

        if (!\is_array($entitiesToReindex)) {
            $entitiesToReindex = [];
        }

        foreach ($this->registry->getDefinitions() as $definition) {
            $entityDefinition = $definition->getEntityDefinition();
            $indexName = $this->elasticsearchHelper->getIndexName($entityDefinition);

            try {
                $this->client->indices()->putMapping([
                    'index' => $indexName,
                    'body' => $this->indexMappingProvider->build($definition, $context),
                ]);
            } catch (BadRequestHttpException $exception) {
                $errorMessage = $exception->getMessage();

                // These putMapping errors cannot be resolved on a live index, so the entity is
                // scheduled for a reindex into a freshly created index.
                foreach (self::REINDEXABLE_MAPPING_ERRORS as $needle) {
                    if (str_contains($errorMessage, $needle)) {
                        $entitiesToReindex[] = $entityDefinition->getEntityName();
                        $exception = ElasticsearchProductException::cannotChangeFieldType($exception);

                        break;
                    }
                }

                $this->elasticsearchHelper->logAndThrowException($exception);
            } catch (NotFoundHttpException $exception) {
                $this->elasticsearchHelper->logAndThrowException($exception);
            }
        }

        if ($entitiesToReindex !== []) {
            $this->storage->set(SystemUpdateListener::CONFIG_KEY, \array_values(\array_unique($entitiesToReindex)));
        }
    }
}
