<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Product;

use OpenSearch\Client;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\Log\Package;
use Shopware\Elasticsearch\Framework\ElasticsearchHelper;
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
        private readonly ProductDefinition $productDefinition,
        private readonly Client $client,
        private readonly MessageBusInterface $bus
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            'sales_channel_language.written' => 'onSalesChannelWritten',
        ];
    }

    public function onSalesChannelWritten(EntityWrittenEvent $event): void
    {
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
}
