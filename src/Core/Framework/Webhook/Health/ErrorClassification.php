<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Webhook\Health;

use Shopware\Core\Framework\Log\Package;

/**
 * The outcome of classifying a single delivery attempt. Each case maps to one webhook-health
 * effect: transient (network/server/rate-limit) counts toward DEGRADED; non-transient auth/endpoint
 * → SUSPENDED; payload rejection → no health effect (the sender is at fault).
 *
 * @internal
 */
#[Package('framework')]
enum ErrorClassification: string
{
    case Success = 'success';
    case TransientNetwork = 'transient_network';
    case TransientServer = 'transient_server';
    case TransientRateLimit = 'transient_rate_limit';
    case NonTransientPayload = 'non_transient_payload';
    case NonTransientAuth = 'non_transient_auth';
    case NonTransientEndpoint = 'non_transient_endpoint';
}
