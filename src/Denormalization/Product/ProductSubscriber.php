<?php

namespace Shopware\Denormalization\Product;

use Shopware\Product\Event\ProductWrittenEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class ProductSubscriber implements EventSubscriberInterface
{
    /**
     * @var ProductIndexer
     */
    private $indexer;

    public function __construct(ProductIndexer $indexer)
    {
        $this->indexer = $indexer;
    }

    public static function getSubscribedEvents()
    {
        return [
            ProductWrittenEvent::NAME => 'indexProducts',
        ];
    }

    public function indexProducts(ProductWrittenEvent $event)
    {
        $this->indexer->index($event->getProductUuids(), $event->getContext());
    }
}
