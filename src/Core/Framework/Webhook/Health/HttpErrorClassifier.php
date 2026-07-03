<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Webhook\Health;

use Shopware\Core\Framework\Log\Package;

/**
 * Maps one delivery outcome to one {@see ErrorClassification}: either the HTTP status
 * code, or the transport failure when there was no response at all (status 0). Pure:
 * no state, no I/O, no `Retry-After` parsing (retry timing stays in the delivery service).
 *
 * Transient results (network, server, rate limit, redirect, 404) count toward DEGRADED.
 * A 401/403 feeds the non-transient suspension streak. A 410 suspends immediately. A
 * payload rejection (400, or any other unlisted 4xx) has **no** health effect: the
 * sender is at fault, not the endpoint, so one malformed event can never disable a
 * good endpoint.
 *
 * @internal
 */
#[Package('framework')]
final class HttpErrorClassifier implements ErrorClassifier
{
    public function classify(int $statusCode, ?\Throwable $exception = null): ErrorClassification
    {
        // No HTTP response: a transport-level failure such as DNS, connection refused, read
        // timeout, or a TLS handshake error ($exception carries it). All of these are
        // transient: a certificate-renewal blip or DNS jitter must not suspend an endpoint.
        // A transport fault that persists still escalates through the normal DEGRADED threshold.
        if ($statusCode === 0) {
            return ErrorClassification::TransientNetwork;
        }

        return match (true) {
            $statusCode >= 200 && $statusCode < 300 => ErrorClassification::Success,
            // An unfollowed redirect is an endpoint configuration problem, not bad message
            // content. It escalates through DEGRADED instead of failing invisibly forever.
            $statusCode >= 300 && $statusCode < 400 => ErrorClassification::TransientRedirect,
            $statusCode === 429 => ErrorClassification::TransientRateLimit,
            // 404 is transient: usually a deploy or config change in flight, which heals
            // itself. A 404 that persists still escalates through the DEGRADED budget.
            $statusCode === 404, $statusCode === 408, $statusCode >= 500 && $statusCode < 600 => ErrorClassification::TransientServer,
            // Auth rejections suspend only as a streak: one spurious WAF/CDN 401 is a blip, not a verdict.
            $statusCode === 401, $statusCode === 403 => ErrorClassification::NonTransientAuth,
            // 410 Gone is the endpoint saying it is retired — suspend immediately.
            $statusCode === 410 => ErrorClassification::NonTransientEndpoint,
            // 400 and every other unlisted 4xx is about this specific message, so no health impact.
            default => ErrorClassification::NonTransientPayload,
        };
    }
}
