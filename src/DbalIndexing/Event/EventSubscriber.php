<?php declare(strict_types=1);

namespace Shopware\DbalIndexing\Event;

use Shopware\Api\Category\Event\Category\CategoryWrittenEvent;
use Shopware\Api\Product\Event\Product\ProductWrittenEvent;
use Shopware\DbalIndexing\Indexer\ShopIndexer;
use Shopware\Framework\Event\NestedEventCollection;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class EventSubscriber implements EventSubscriberInterface
{
    /**
     * @var ShopIndexer
     */
    private $indexer;

    public function __construct(ShopIndexer $indexer)
    {
        $this->indexer = $indexer;
    }

    public static function getSubscribedEvents()
    {
        return [
            ProductWrittenEvent::NAME => 'productWritten',
            CategoryWrittenEvent::NAME => 'categoryWritten',
        ];
    }

    public function productWritten(ProductWrittenEvent $event)
    {
        $this->indexer->refresh(
            new NestedEventCollection([$event]),
            $event->getContext()
        );
    }

    public function categoryWritten(CategoryWrittenEvent $event)
    {
        $this->indexer->refresh(
            new NestedEventCollection([$event]),
            $event->getContext()
        );
    }
}
