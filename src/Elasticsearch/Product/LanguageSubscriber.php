<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Product;

use OpenSearch\Client;
use OpenSearch\Exception\BadRequestHttpException;
use OpenSearch\Exception\NotFoundHttpException;
use Shopware\Core\Framework\Adapter\Storage\AbstractKeyValueStorage;
use Shopware\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\Log\Package;
use Shopware\Elasticsearch\Framework\ElasticsearchHelper;
use Shopware\Elasticsearch\Framework\ElasticsearchRegistry;
use Shopware\Elasticsearch\Framework\Indexing\IndexMappingUpdater;
use Shopware\Elasticsearch\Framework\SystemUpdateListener;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 * When a language is created, we need to trigger an indexing for that
 */
#[Package('framework')]
class LanguageSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly ElasticsearchHelper $elasticsearchHelper,
        private readonly ElasticsearchRegistry $registry,
        private readonly Client $client,
        private readonly AbstractKeyValueStorage $storage,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            'language.written' => 'onLanguageWritten',
        ];
    }

    public function onLanguageWritten(EntityWrittenEvent $event): void
    {
        if (!$this->elasticsearchHelper->allowIndexing()) {
            return;
        }

        $context = $event->getContext();
        $entitiesToReindex = $this->storage->get(SystemUpdateListener::CONFIG_KEY, []) ?? [];

        if (\is_string($entitiesToReindex)) {
            $entitiesToReindex = \json_decode($entitiesToReindex, true);
        }

        if (!\is_array($entitiesToReindex)) {
            $entitiesToReindex = [];
        }

        foreach ($event->getResults()->only(EntityWriteResult::OPERATION_INSERT) as $writeResult) {
            foreach ($this->registry->getDefinitions() as $definition) {
                $indexName = $this->elasticsearchHelper->getIndexName($definition->getEntityDefinition());

                // index doesn't exist, don't need to do anything
                if (!$this->client->indices()->exists(['index' => $indexName])) {
                    continue;
                }

                try {
                    $this->client->indices()->putMapping([
                        'index' => $indexName,
                        'body' => [
                            'properties' => $definition->getMapping($context)['properties'],
                        ],
                    ]);
                } catch (BadRequestHttpException $exception) {
                    $errorMessage = $exception->getMessage();

                    foreach (IndexMappingUpdater::REINDEXABLE_MAPPING_ERRORS as $needle) {
                        if (str_contains($errorMessage, $needle)) {
                            $entitiesToReindex[] = $definition->getEntityDefinition()->getEntityName();
                            $exception = ElasticsearchProductException::cannotChangeFieldType($exception);

                            break;
                        }
                    }

                    $this->elasticsearchHelper->logAndThrowException($exception);
                } catch (NotFoundHttpException $exception) {
                    $this->elasticsearchHelper->logAndThrowException($exception);
                }
            }
        }

        if ($entitiesToReindex !== []) {
            $this->storage->set(SystemUpdateListener::CONFIG_KEY, \array_values(\array_unique($entitiesToReindex)));
        }
    }
}
