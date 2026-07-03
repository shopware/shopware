<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Webhook\Event;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Webhook\Health\EndpointState;
use Shopware\Core\Framework\Webhook\Health\SuspensionCause;

/**
 * A webhook entered SUSPENDED. Best-effort and post-commit: advisory only, the `webhook_health` row
 * is the truth, and a listener failure never affects the transition. `suspendedSince` is the
 * episode anchor — set once on the first suspension and unchanged across re-suspension — and keys
 * the one-notification-per-suspension rule; `occurredAt` is this transition's own time (on
 * re-suspension the two differ). `webhookName`/`eventName` are null only when the webhook row
 * vanished between the transition and the emission lookup.
 *
 * @internal
 */
#[Package('framework')]
final readonly class WebhookSuspendedEvent
{
    public function __construct(
        public string $webhookId,
        public ?string $appId,
        public EndpointState $fromState,
        public \DateTimeImmutable $suspendedSince,
        public SuspensionCause $cause,
        public ?string $webhookName,
        public ?string $eventName,
        public \DateTimeImmutable $occurredAt,
    ) {
    }
}
