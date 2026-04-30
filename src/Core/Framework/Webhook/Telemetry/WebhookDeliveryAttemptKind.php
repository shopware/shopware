<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Webhook\Telemetry;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
enum WebhookDeliveryAttemptKind: string
{
    case FIRST_ATTEMPT = 'first_attempt';
    case RETRY = 'retry';

    public static function fromExecutionCount(int $executionCount): self
    {
        return $executionCount === 1 ? self::FIRST_ATTEMPT : self::RETRY;
    }
}
