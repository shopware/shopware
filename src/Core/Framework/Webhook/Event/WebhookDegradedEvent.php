<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Webhook\Event;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Webhook\Health\EndpointState;

/**
 * A webhook entered DEGRADED. Best-effort and post-commit: advisory only, the `webhook_health` row
 * is the truth, and a listener failure never affects the transition. DEGRADED is routine
 * self-healing — no Admin notification is attached to this event.
 *
 * @internal
 */
#[Package('framework')]
final readonly class WebhookDegradedEvent
{
    public function __construct(
        public string $webhookId,
        public ?string $appId,
        public EndpointState $fromState,
    ) {
    }
}
