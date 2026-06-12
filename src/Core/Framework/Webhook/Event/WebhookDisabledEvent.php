<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Webhook\Event;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Webhook\Health\DisabledOrigin;
use Shopware\Core\Framework\Webhook\Health\EndpointState;

/**
 * A webhook entered DISABLED — by the 7-day escalation or an operator kill; `origin` says which.
 * Best-effort and post-commit: advisory only, the `webhook_health` row is the truth, and a listener
 * failure never affects the transition. Entering DISABLED always notifies the Admin.
 *
 * @internal
 */
#[Package('framework')]
final readonly class WebhookDisabledEvent
{
    public function __construct(
        public string $webhookId,
        public ?string $appId,
        public EndpointState $fromState,
        public DisabledOrigin $origin,
    ) {
    }
}
