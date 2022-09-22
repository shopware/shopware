<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Indexing\Subscriber;

use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexerRegistry;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 */
class EntityIndexingSubscriber implements EventSubscriberInterface
{
    private EntityIndexerRegistry $indexerRegistry;

    public function __construct(EntityIndexerRegistry $indexerRegistry)
    {
        $this->indexerRegistry = $indexerRegistry;
    }

    public static function getSubscribedEvents(): array
    {
        return [EntityWrittenContainerEvent::class => [['refreshIndex', 1000]]];
    }

    public function refreshIndex(EntityWrittenContainerEvent $event): void
    {
        $this->indexerRegistry->refresh($event);
    }
}
