<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Webhook\Health;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
final class HttpErrorClassifier
{
    public function classify(int $statusCode): ErrorClassification
    {
        // Status 0 means that no HTTP response was received.
        if ($statusCode === 0) {
            return ErrorClassification::TransientNetwork;
        }

        return match (true) {
            $statusCode >= 200 && $statusCode < 300 => ErrorClassification::Success,
            $statusCode >= 300 && $statusCode < 400 => ErrorClassification::TransientRedirect,
            $statusCode === 429 => ErrorClassification::TransientRateLimit,
            $statusCode === 404, $statusCode === 408, $statusCode >= 500 && $statusCode < 600 => ErrorClassification::TransientServer,
            default => ErrorClassification::NonTransientPayload,
        };
    }
}
