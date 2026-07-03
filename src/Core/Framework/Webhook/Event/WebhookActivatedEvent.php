<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Webhook\Event;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Webhook\Health\EndpointState;

/**
 * A webhook reached HEALTHY, by any path — the trigger says which. Best-effort and post-commit:
 * advisory only, the `webhook_health` row is the truth, and a listener failure never affects the
 * transition. `clearedSuspendedSince` is non-null when this recovery ends a suspension episode
 * (the value the transition cleared) — the key for the Admin recovery notice.
 * `webhookName`/`eventName` are null only when the webhook row vanished between the transition and
 * the emission lookup.
 *
 * @internal
 */
#[Package('framework')]
final readonly class WebhookActivatedEvent
{
    public function __construct(
        public string $webhookId,
        public ?string $appId,
        public EndpointState $fromState,
        public WebhookActivationTrigger $trigger,
        public ?string $webhookName,
        public ?string $eventName,
        public \DateTimeImmutable $occurredAt,
        public ?\DateTimeImmutable $clearedSuspendedSince = null,
    ) {
    }
}
