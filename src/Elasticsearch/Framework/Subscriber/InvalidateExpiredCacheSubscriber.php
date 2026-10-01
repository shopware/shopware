<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Framework\Subscriber;

use Shopware\Core\Framework\Api\Event\InvalidateExpiredCacheRequestEvent;
use Shopware\Core\Framework\Log\Package;
use Shopware\Elasticsearch\Framework\Indexing\IndexManager;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 */
#[Package('framework')]
class InvalidateExpiredCacheSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly IndexManager $indexManager
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            InvalidateExpiredCacheRequestEvent::class => 'refreshOpensearchIndices',
        ];
    }

    public function refreshOpensearchIndices(InvalidateExpiredCacheRequestEvent $event): void
    {
        if ($event->request->query->getBoolean('refreshOpenSearch')) {
            $this->indexManager->refreshIndices();
        }
    }
}
