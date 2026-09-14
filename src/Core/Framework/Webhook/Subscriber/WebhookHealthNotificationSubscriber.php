<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Webhook\Subscriber;

use Doctrine\DBAL\Connection;
use Psr\Clock\ClockInterface;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\Hasher;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Webhook\Event\WebhookActivatedEvent;
use Shopware\Core\Framework\Webhook\Event\WebhookDisabledEvent;
use Shopware\Core\Framework\Webhook\Event\WebhookSuspendedEvent;
use Shopware\Core\Framework\Webhook\Health\DisabledOrigin;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 *
 * @codeCoverageIgnore
 *
 * @see \Shopware\Tests\Integration\Core\Framework\Webhook\Subscriber\WebhookHealthNotificationSubscriberTest
 */
#[Package('framework')]
class WebhookHealthNotificationSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly Connection $connection,
        private readonly ClockInterface $clock,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            WebhookSuspendedEvent::class => 'onSuspended',
            WebhookDisabledEvent::class => 'onDisabled',
            WebhookActivatedEvent::class => 'onActivated',
        ];
    }

    public function onSuspended(WebhookSuspendedEvent $event): void
    {
        if (!Feature::isActive('WEBHOOKS_REWORK')) {
            return;
        }

        // The stable episode anchor and INSERT IGNORE yield one notification per suspension.
        $this->notify(
            $this->episodeNotificationId($event->webhookId, 'suspended', $event->suspendedSince),
            'warning',
            \sprintf(
                'Webhook "%s" was suspended after repeated delivery failures. New events are not delivered; recovery is retried automatically.',
                $event->webhookName ?? $event->webhookId
            )
        );
    }

    public function onDisabled(WebhookDisabledEvent $event): void
    {
        if (!Feature::isActive('WEBHOOKS_REWORK')) {
            return;
        }

        $webhookName = $event->webhookName ?? $event->webhookId;
        if ($event->origin === DisabledOrigin::Operator) {
            $message = \sprintf('Webhook "%s" was disabled by an operator.', $webhookName);
        } else {
            $message = \sprintf(
                'Webhook "%s" was disabled automatically after exceeding the suspension limit. It needs manual attention.',
                $webhookName
            );
        }

        $this->notify(Uuid::randomHex(), 'error', $message);
    }

    public function onActivated(WebhookActivatedEvent $event): void
    {
        if (!Feature::isActive('WEBHOOKS_REWORK') || $event->clearedSuspendedSince === null) {
            return;
        }

        $this->notify(
            $this->episodeNotificationId($event->webhookId, 'recovered', $event->clearedSuspendedSince),
            'positive',
            \sprintf('Webhook "%s" recovered and is delivering again.', $event->webhookName ?? $event->webhookId)
        );
    }

    private function notify(string $idHex, string $status, string $message): void
    {
        $this->connection->executeStatement(
            'INSERT IGNORE INTO notification (id, status, message, admin_only, created_at)
             VALUES (:id, :status, :message, 1, :now)',
            [
                'id' => Uuid::fromHexToBytes($idHex),
                'status' => $status,
                'message' => $message,
                'now' => $this->clock->now()->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]
        );
    }

    private function episodeNotificationId(string $webhookId, string $kind, \DateTimeImmutable $episodeAnchor): string
    {
        return Hasher::hash(\sprintf('webhook-health-%s-%s-%d', $kind, $webhookId, $episodeAnchor->getTimestamp()), 'md5');
    }
}
