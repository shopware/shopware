<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Product;

use Shopware\Core\Content\Product\Events\ProductIndexerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Elasticsearch\Framework\Indexing\ElasticsearchIndexer;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class ProductUpdater implements EventSubscriberInterface
{
    private ElasticsearchIndexer $indexer;

    private EntityDefinition $definition;

    public function __construct(ElasticsearchIndexer $indexer, EntityDefinition $definition)
    {
        $this->indexer = $indexer;
        $this->definition = $definition;
    }

    public static function getSubscribedEvents()
    {
        return [
            ProductIndexerEvent::class => 'update',
        ];
    }

    public function update(ProductIndexerEvent $event): void
    {
        $this->indexer->updateIds(
            $this->definition,
            array_unique(array_merge($event->getIds(), $event->getChildrenIds()))
        );
    }
}
