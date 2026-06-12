<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Webhook\Message;

use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Messenger\Stamp\StampInterface;

/**
 * Marks a webhook dispatch to be persisted as a held (`paused`) outbox row instead of a claimable
 * one — the WEBHOOKS_REWORK dispatch gate's Hold decision (#16565). WebhookTransport reads this
 * stamp at its existing insert point without knowing about health, so the transport stays
 * health-agnostic.
 *
 * @internal
 */
#[Package('framework')]
final class HeldDeliveryStamp implements StampInterface
{
}
