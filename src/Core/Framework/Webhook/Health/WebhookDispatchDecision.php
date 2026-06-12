<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Webhook\Health;

use Shopware\Core\Framework\Log\Package;

/**
 * What the dispatch gate does with one event for one webhook. Each outcome maps directly
 * to how (or whether) the outbox row is written:
 *
 * - {@see self::Deliver} — write a claimable row (`queued`); the transport delivers it (HEALTHY).
 * - {@see self::Hold}    — write a held row (`paused`); the transport ignores it until health
 *                          releases it (DEGRADED).
 * - {@see self::Skip}    — write nothing; the event is dropped for this webhook (SUSPENDED/DISABLED).
 *
 * The {@see EndpointHealth} implementation decides which state maps to which decision.
 * With no EndpointHealth wired (null), the default is {@see self::Deliver}.
 *
 * @internal
 */
#[Package('framework')]
enum WebhookDispatchDecision
{
    case Deliver;
    case Hold;
    case Skip;
}
