<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Product;

use Shopware\Core\Content\Product\Events\ProductIndexerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\Log\Package;
use Shopware\Elasticsearch\Framework\Indexing\ElasticsearchIndexer;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 */
#[Package('core')]
class ProductUpdater implements EventSubscriberInterface
{
    /**
     * @internal
     */
    public function __construct(
        private readonly ElasticsearchIndexer $indexer,
        private readonly EntityDefinition $definition
    ) {
    }

    /**
     * @return array<string, string|array{0: string, 1: int}|list<array{0: string, 1?: int}>>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            ProductIndexerEvent::class => 'update',
        ];
    }

    public function update(ProductIndexerEvent $event): void
    {
        $this->indexer->updateIds($this->definition, $event->getIds());
    }
}
