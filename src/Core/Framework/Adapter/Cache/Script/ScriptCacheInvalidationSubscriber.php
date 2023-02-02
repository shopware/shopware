<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Cache\Script;

use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\Script\Execution\ScriptExecutor;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 */
class ScriptCacheInvalidationSubscriber implements EventSubscriberInterface
{
    private ScriptExecutor $scriptExecutor;

    public function __construct(ScriptExecutor $scriptExecutor)
    {
        $this->scriptExecutor = $scriptExecutor;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            EntityWrittenContainerEvent::class => 'executeCacheInvalidationHook',
        ];
    }

    public function executeCacheInvalidationHook(EntityWrittenContainerEvent $event): void
    {
        $this->scriptExecutor->execute(
            new CacheInvalidationHook($event)
        );
    }
}
