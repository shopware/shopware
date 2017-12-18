<?php declare(strict_types=1);

namespace Shopware\DbalIndexing\Event;

use Shopware\Api\Entity\Write\GenericWrittenEvent;
use Shopware\DbalIndexing\Indexer\ShopIndexer;
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
            GenericWrittenEvent::NAME => 'refresh',
        ];
    }

    public function refresh(GenericWrittenEvent $event)
    {
        $this->indexer->refresh($event);
    }
}
