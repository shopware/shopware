<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Product;

use OpenSearch\Client;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Elasticsearch\Framework\ElasticsearchHelper;
use Shopware\Elasticsearch\Framework\ElasticsearchRegistry;
use Shopware\Elasticsearch\Framework\Indexing\ElasticsearchLanguageIndexIteratorMessage;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * @internal
 * When an language is created, we need to trigger an indexing for that
 */
#[Package('core')]
class LanguageSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly ElasticsearchHelper $elasticsearchHelper,
        private readonly ElasticsearchRegistry $registry,
        private readonly Client $client,
        private readonly ProductDefinition $productDefinition,
        private readonly MessageBusInterface $bus
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        if (Feature::isActive('ES_MULTILINGUAL_INDEX')) {
            return [
                'language.written' => 'onLanguageWritten',
            ];
        }

        return [
            'sales_channel_language.written' => 'onSalesChannelWritten',
        ];
    }

    /**
     * @deprecated tag:v6.6.0 - reason:remove-subscriber -  method will be removed
     */
    public function onSalesChannelWritten(EntityWrittenEvent $event): void
    {
        if (Feature::isActive('ES_MULTILINGUAL_INDEX')) {
            return;
        }

        if (!$this->elasticsearchHelper->allowIndexing()) {
            return;
        }

        foreach ($event->getWriteResults() as $writeResult) {
            if ($writeResult->getOperation() !== EntityWriteResult::OPERATION_INSERT) {
                continue;
            }

            $languageId = $writeResult->getProperty('languageId');

            $esIndex = $this->elasticsearchHelper->getIndexName($this->productDefinition, $languageId);

            // index exists, don't need to do anything
            if ($this->client->indices()->exists(['index' => $esIndex])) {
                continue;
            }

            $this->bus->dispatch(new ElasticsearchLanguageIndexIteratorMessage($languageId));
        }
    }

    public function onLanguageWritten(EntityWrittenEvent $event): void
    {
        if (!$this->elasticsearchHelper->allowIndexing()) {
            return;
        }

        $context = $event->getContext();

        foreach ($event->getWriteResults() as $writeResult) {
            if ($writeResult->getOperation() !== EntityWriteResult::OPERATION_INSERT) {
                continue;
            }

            foreach ($this->registry->getDefinitions() as $definition) {
                $indexName = $this->elasticsearchHelper->getIndexName($definition->getEntityDefinition());

                // index doesn't exist, don't need to do anything
                if (!$this->client->indices()->exists(['index' => $indexName])) {
                    continue;
                }

                $this->client->indices()->putMapping([
                    'index' => $indexName,
                    'body' => [
                        'properties' => $definition->getMapping($context)['properties'],
                    ],
                ]);
            }
        }
    }
}
