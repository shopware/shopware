<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Product;

use Shopware\Core\Content\Product\Events\ProductIndexerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Elasticsearch\Framework\Indexing\ElasticsearchIndexer;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @package core
 *
 * @deprecated tag:v6.5.0 - reason:becomes-internal - EventSubscribers will become internal in v6.5.0
 */
class ProductUpdater implements EventSubscriberInterface
{
    private ElasticsearchIndexer $indexer;

    private EntityDefinition $definition;

    /**
     * @internal
     */
    public function __construct(ElasticsearchIndexer $indexer, EntityDefinition $definition)
    {
        $this->indexer = $indexer;
        $this->definition = $definition;
    }

    /**
     * @return array<string, string|array{0: string, 1: int}|list<array{0: string, 1?: int}>>
     */
    public static function getSubscribedEvents()
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
