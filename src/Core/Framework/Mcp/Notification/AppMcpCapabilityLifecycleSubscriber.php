<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Mcp\Notification;

use Shopware\Core\Framework\App\Event\AppActivatedEvent;
use Shopware\Core\Framework\App\Event\AppChangedEvent;
use Shopware\Core\Framework\App\Event\AppDeactivatedEvent;
use Shopware\Core\Framework\App\Event\AppDeletedEvent;
use Shopware\Core\Framework\App\Event\AppInstalledEvent;
use Shopware\Core\Framework\App\Event\AppUpdatedEvent;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @experimental stableVersion:v6.8.0
 *
 * @internal
 */
#[Package('framework')]
class AppMcpCapabilityLifecycleSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly AppMcpCapabilityDetector $capabilityDetector,
        private readonly McpListChangedNotifier $notifier,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            AppActivatedEvent::class => 'onAppChanged',
            AppDeactivatedEvent::class => 'onAppChanged',
            AppDeletedEvent::class => 'onAppDeleted',
            AppInstalledEvent::class => 'onAppFeaturesChanged',
            AppUpdatedEvent::class => 'onAppFeaturesChanged',
        ];
    }

    public function onAppChanged(AppChangedEvent $event): void
    {
        $this->notifyForApp($event->getApp()->getId());
    }

    public function onAppDeleted(AppDeletedEvent $event): void
    {
        $this->notifyForApp($event->getAppId());
    }

    public function onAppFeaturesChanged(): void
    {
        // App feature storage has already been synchronized when these events are
        // dispatched. Notify all capability lists so removals are covered as well.
        $this->notifier->notify(new McpListChangedNotificationSet(
            tools: true,
            resources: true,
            prompts: true,
        ));
    }

    private function notifyForApp(string $appId): void
    {
        $this->notifier->notify($this->capabilityDetector->persistedForApp($appId));
    }
}
