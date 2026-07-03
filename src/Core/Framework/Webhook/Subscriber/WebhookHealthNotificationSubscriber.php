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
 * Tells the operator when the breaker trips and when it recovers (ADR §Lifecycle events &
 * trip notification). A breaker that trips silently turns hours of dropped events into a
 * month-end surprise.
 *
 * One Admin notification per suspension episode, keyed on the unchanged `suspended_since`:
 * a webhook that re-trips before ever reaching HEALTHY does not alarm again. Repeated
 * alarms from a flapping endpoint teach operators to ignore the channel. Entering DISABLED
 * always notifies. A short recovery notice closes the loop. Messages carry the webhook
 * name and state only — never URLs, payloads, or exception text. DEGRADED is routine
 * self-healing: events yes, human alarm no.
 *
 * @internal
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

        $this->notify(
            // Deterministic id keyed on the episode anchor: with INSERT IGNORE, a second trip of
            // the same suspension (suspended_since unchanged) is a no-op — one notification per episode.
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

        $message = $event->origin === DisabledOrigin::Operator
            ? \sprintf('Webhook "%s" was disabled by an operator.', $event->webhookName ?? $event->webhookId)
            : \sprintf(
                'Webhook "%s" was disabled automatically after exceeding the suspension limit. It needs manual attention.',
                $event->webhookName ?? $event->webhookId
            );

        $this->notify(Uuid::randomHex(), 'error', $message);
    }

    public function onActivated(WebhookActivatedEvent $event): void
    {
        if (!Feature::isActive('WEBHOOKS_REWORK') || $event->clearedSuspendedSince === null) {
            return;
        }

        // The short recovery notice that closes the suspension loop. Deliberately without counts
        // or remedy instructions: acting on the gap is the app vendor's job, and the vendor's
        // record is the app-facing state API and the replayable FAILED rows.
        $this->notify(
            $this->episodeNotificationId($event->webhookId, 'recovered', $event->clearedSuspendedSince),
            'positive',
            \sprintf('Webhook "%s" recovered and is delivering again.', $event->webhookName ?? $event->webhookId)
        );
    }

    private function notify(string $idHex, string $status, string $message): void
    {
        // Raw INSERT IGNORE: the deterministic primary key IS the dedup. Re-emissions of the
        // same episode collapse without a read-modify-write race.
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
