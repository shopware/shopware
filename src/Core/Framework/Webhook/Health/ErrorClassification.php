<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Webhook\Health;

use Shopware\Core\Framework\Log\Package;

/**
 * The result of classifying one delivery attempt. Each case has one health effect:
 * the transient cases (network, server, rate limit, redirect) count toward DEGRADED;
 * a non-transient auth failure feeds the suspension streak; a non-transient endpoint
 * failure (410) suspends immediately; a payload rejection has no health effect,
 * because the sender is at fault, not the endpoint.
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
    case TransientRedirect = 'transient_redirect';
    case NonTransientPayload = 'non_transient_payload';
    case NonTransientAuth = 'non_transient_auth';
    case NonTransientEndpoint = 'non_transient_endpoint';

    public function isTransient(): bool
    {
        return match ($this) {
            self::TransientNetwork, self::TransientServer, self::TransientRateLimit, self::TransientRedirect => true,
            default => false,
        };
    }
}
