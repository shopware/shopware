<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ScheduledTask\Subscriber;

use Psr\Cache\CacheItemPoolInterface;
use Shopware\Core\Framework\Plugin\Event\PluginPostActivateEvent;
use Shopware\Core\Framework\Plugin\Event\PluginPostDeactivateEvent;
use Shopware\Core\Framework\ScheduledTask\Registry\TaskRegistry;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\EventListener\StopWorkerOnRestartSignalListener;

class PluginLifecycleSubscriber implements EventSubscriberInterface
{
    /**
     * @var TaskRegistry
     */
    private $registry;

    /**
     * @var CacheItemPoolInterface
     */
    private $restartSignalCachePool;

    public function __construct(TaskRegistry $registry, CacheItemPoolInterface $restartSignalCachePool)
    {
        $this->registry = $registry;
        $this->restartSignalCachePool = $restartSignalCachePool;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            PluginPostActivateEvent::class => 'afterPluginStateChange',
            PluginPostDeactivateEvent::class => 'afterPluginStateChange',
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
