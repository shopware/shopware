<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Update\Subscriber;

use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Notification\NotificationService;
use Shopware\Core\Framework\Update\Event\UpdatePostFinishEvent;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 */
#[Package('framework')]
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
        if ($event->getPostUpdateMessage() === '') {
            return;
        }

        $source = $event->getContext()->getSource();
        $integrationId = null;
        $createdByUserId = null;
        if ($source instanceof AdminApiSource) {
            $integrationId = $source->getIntegrationId();
            $createdByUserId = $source->getUserId();
        }
        $wrappedMessage = \wordwrap($event->getPostUpdateMessage(), 255, "\v", true);

        foreach (\array_map(fn (string $message): string => trim($message, ' '), \array_reverse(\explode("\v", $wrappedMessage))) as $partialMessage) {
            $this->notificationService->createNotification(
                [
                    'id' => Uuid::randomHex(),
                    'status' => 'warning',
                    'message' => $partialMessage,
                    'adminOnly' => true,
                    'requiredPrivileges' => [],
                    'createdByIntegrationId' => $integrationId,
                    'createdByUserId' => $createdByUserId,
                ],
                $event->getContext()
            );
        }
    }
}
