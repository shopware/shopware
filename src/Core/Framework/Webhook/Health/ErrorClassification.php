<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Webhook\Health;

use Shopware\Core\Framework\Log\Package;

/**
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

    /**
     * @param int $statusCode 0 when no HTTP response was received
     */
    public static function fromStatusCode(int $statusCode): self
    {
        return match (true) {
            $statusCode === 0 => self::TransientNetwork,
            $statusCode >= 200 && $statusCode < 300 => self::Success,
            $statusCode >= 300 && $statusCode < 400 => self::TransientRedirect,
            $statusCode === 429 => self::TransientRateLimit,
            $statusCode === 404, $statusCode === 408, $statusCode >= 500 && $statusCode < 600 => self::TransientServer,
            $statusCode === 401, $statusCode === 403 => self::NonTransientAuth,
            $statusCode === 410 => self::NonTransientEndpoint,
            default => self::NonTransientPayload,
        };
    }

    public function isTransient(): bool
    {
        return match ($this) {
            self::TransientNetwork, self::TransientServer, self::TransientRateLimit, self::TransientRedirect => true,
            default => false,
        };
    }
}
