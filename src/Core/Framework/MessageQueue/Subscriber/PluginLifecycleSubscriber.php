<?php declare(strict_types=1);

namespace Shopware\Core\Framework\MessageQueue\Subscriber;

use Psr\Cache\CacheItemPoolInterface;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\Registry\TaskRegistry;
use Shopware\Core\Framework\Plugin\Event\PluginPostActivateEvent;
use Shopware\Core\Framework\Plugin\Event\PluginPostDeactivateEvent;
use Shopware\Core\Framework\Plugin\Event\PluginPostUpdateEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\EventListener\StopWorkerOnRestartSignalListener;

/**
 * @internal
 */
#[Package('system-settings')]
final class PluginLifecycleSubscriber implements EventSubscriberInterface
{
    /**
     * @internal
     */
    public function __construct(
        private readonly TaskRegistry $registry,
        private readonly CacheItemPoolInterface $restartSignalCachePool
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            PluginPostActivateEvent::class => 'afterPluginStateChange',
            PluginPostDeactivateEvent::class => 'afterPluginStateChange',
            PluginPostUpdateEvent::class => 'afterPluginStateChange',
        ];
    }

    public function afterPluginStateChange(): void
    {
        $this->registry->registerTasks();

        // signal worker restart
        $cacheItem = $this->restartSignalCachePool->getItem(StopWorkerOnRestartSignalListener::RESTART_REQUESTED_TIMESTAMP_KEY);
        $cacheItem->set(microtime(true));
        $this->restartSignalCachePool->save($cacheItem);
    }
}
