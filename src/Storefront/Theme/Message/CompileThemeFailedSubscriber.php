<?php declare(strict_types=1);

namespace Shopware\Storefront\Theme\Message;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Notification\NotificationService;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Storefront\Theme\ThemeService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;

/**
 * Reacts to a deferred theme compilation that Messenger has finally given up on (dead-lettered).
 * Notifying from inside the handler would fire on every retry attempt and leave the pending marker
 * dangling, so both the user notification and the marker cleanup happen here, exactly once.
 *
 * @internal
 */
#[Package('discovery')]
class CompileThemeFailedSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly NotificationService $notificationService,
        private readonly SystemConfigService $systemConfigService,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            WorkerMessageFailedEvent::class => 'onWorkerMessageFailed',
        ];
    }

    public function onWorkerMessageFailed(WorkerMessageFailedEvent $event): void
    {
        // Act only on the terminal failure, not on the retries that precede it.
        if ($event->willRetry()) {
            return;
        }

        $message = $event->getEnvelope()->getMessage();
        if (!$message instanceof CompileThemeMessage || !$message->isAssign()) {
            return;
        }

        // Stop the Administration from polling for a switch that will never be applied, but keep a
        // newer request intact by clearing the marker only while it still points at the failed theme.
        if ($this->systemConfigService->getString(ThemeService::CONFIG_KEY_PENDING_THEME, $message->getSalesChannelId()) === $message->getThemeId()) {
            $this->systemConfigService->set(ThemeService::CONFIG_KEY_PENDING_THEME, '', $message->getSalesChannelId(), false);
        }

        if ($message->getContext()->getScope() !== Context::USER_SCOPE) {
            return;
        }

        $this->notificationService->createNotification(
            [
                'id' => Uuid::randomHex(),
                'status' => 'warning',
                'message' => 'sw-theme-manager.detail.asyncCompilation.error',
                'requiredPrivileges' => [],
            ],
            $message->getContext()
        );
    }
}
