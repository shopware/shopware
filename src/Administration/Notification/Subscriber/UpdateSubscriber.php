<?php declare(strict_types=1);

namespace Shopware\Administration\Notification\Subscriber;

use Shopware\Administration\Notification\NotificationService;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Update\Event\UpdatePostFinishEvent;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 */
#[Package('system-settings')]
class UpdateSubscriber implements EventSubscriberInterface
{
    /**
     * @internal
     */
    public function __construct(
        private readonly NotificationService $notificationService
    ) {
    }

    /**
     * @return array<string, string|array{0: string, 1: int}|list<array{0: string, 1?: int}>>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            UpdatePostFinishEvent::class => [
                ['updateFinishedDone', -9999],
            ],
        ];
    }

    /**
     * @internal
     */
    public function updateFinishedDone(UpdatePostFinishEvent $event): void
    {
        $status = 'success';
        $message = 'Updated successfully to version ' . $event->getNewVersion();
        if ($event->getPostUpdateMessage() !== '') {
            $status = 'warning';
            $message .= \PHP_EOL . $event->getPostUpdateMessage();
        }

        $source = $event->getContext()->getSource();
        $integrationId = null;
        $createdByUserId = null;
        if ($source instanceof AdminApiSource) {
            $integrationId = $source->getIntegrationId();
            $createdByUserId = $source->getUserId();
        }

        $this->notificationService->createNotification(
            [
                'id' => Uuid::randomHex(),
                'status' => $status,
                'message' => $message,
                'adminOnly' => true,
                'requiredPrivileges' => [],
                'createdByIntegrationId' => $integrationId,
                'createdByUserId' => $createdByUserId,
            ],
            $event->getContext()
        );
    }
}
