<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Mcp\Notification;

use Shopware\Core\Framework\App\AppDefinition;
use Shopware\Core\Framework\App\Event\AppActivatedEvent;
use Shopware\Core\Framework\App\Event\AppChangedEvent;
use Shopware\Core\Framework\App\Event\AppDeactivatedEvent;
use Shopware\Core\Framework\App\Event\AppDeletedEvent;
use Shopware\Core\Framework\App\Event\AppInstalledEvent;
use Shopware\Core\Framework\App\Event\AppUpdatedEvent;
use Shopware\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Contracts\Service\ResetInterface;

/**
 * @experimental stableVersion:v6.8.0
 *
 * @internal
 *
 * Install, update and activation events fire once the app is stored and active, so they notify right away.
 * Deactivation and removal events fire before `AppManager` switches the app off or deletes it, while its
 * capabilities are still listed. A client that refreshes on such a notification would read the old lists, so
 * this subscriber records what the app contributes and notifies once the app row is stored as inactive or deleted.
 */
#[Package('framework')]
class AppMcpCapabilityLifecycleSubscriber implements EventSubscriberInterface, ResetInterface
{
    /**
     * @var array<string, McpListChangedNotificationSet> keyed by app id
     */
    private array $pendingRemovals = [];

    public function __construct(
        private readonly AppMcpCapabilityDetector $capabilityDetector,
        private readonly McpListChangedNotifier $notifier,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            AppActivatedEvent::class => 'onAppActivated',
            AppDeactivatedEvent::class => 'onAppDeactivating',
            AppDeletedEvent::class => 'onAppDeleting',
            AppInstalledEvent::class => 'onAppInstalledOrUpdated',
            AppUpdatedEvent::class => 'onAppInstalledOrUpdated',
            AppDefinition::ENTITY_NAME . '.written' => 'onAppWritten',
            AppDefinition::ENTITY_NAME . '.deleted' => 'onAppWritten',
        ];
    }

    public function onAppInstalledOrUpdated(): void
    {
        $this->notifier->notify(new McpListChangedNotificationSet(tools: true, resources: true, prompts: true));
    }

    public function onAppActivated(AppChangedEvent $event): void
    {
        $this->notifier->notify($this->capabilityDetector->persistedForApp($event->getApp()->getId()));
    }

    public function onAppDeactivating(AppChangedEvent $event): void
    {
        $this->recordRemoval($event->getApp()->getId());
    }

    public function onAppDeleting(AppDeletedEvent $event): void
    {
        $this->recordRemoval($event->getAppId());
    }

    /**
     * Notifies for a recorded app once its row is stored as inactive or deleted, the state that later list
     * requests and `subscriptions/listen` clients read.
     */
    public function onAppWritten(EntityWrittenEvent $event): void
    {
        if ($this->pendingRemovals === []) {
            return;
        }

        foreach ($event->getWriteResults() as $result) {
            $appId = $result->getPrimaryKey();
            if (!\is_string($appId) || !isset($this->pendingRemovals[$appId]) || !$this->isRemoval($result)) {
                continue;
            }

            $notifications = $this->pendingRemovals[$appId];
            unset($this->pendingRemovals[$appId]);

            $this->notifier->notify($notifications);
        }
    }

    /**
     * Drops recorded removals whose write never happened, for example because the lifecycle failed, so they do not
     * leak into the next request of a long-running worker.
     */
    public function reset(): void
    {
        $this->pendingRemovals = [];
    }

    private function recordRemoval(string $appId): void
    {
        $notifications = $this->capabilityDetector->persistedForApp($appId);
        if (!$notifications->hasChanges()) {
            return;
        }

        $this->pendingRemovals[$appId] = ($this->pendingRemovals[$appId] ?? McpListChangedNotificationSet::none())->merge($notifications);
    }

    /**
     * @param EntityWriteResult<string> $result
     */
    private function isRemoval(EntityWriteResult $result): bool
    {
        return $result->getOperation() === EntityWriteResult::OPERATION_DELETE
            || ($result->getPayload()['active'] ?? null) === false;
    }
}
