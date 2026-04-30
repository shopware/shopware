<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Webhook\Telemetry;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @codeCoverageIgnore Unit tested with \Shopware\Tests\Unit\Core\Framework\Webhook\Service\WebhookOutcomeClassifierTest
 */
#[Package('framework')]
enum WebhookDeliveryOutcome: string
{
    case SUCCESS = 'success';
    case CLIENT_ERROR = 'client_error';
    case SERVER_ERROR = 'server_error';
    case NETWORK_ERROR = 'network_error';

    public static function fromStatusCode(?int $statusCode): self
    {
        if ($statusCode === null) {
            return self::NETWORK_ERROR;
        }

        if ($statusCode >= 400 && $statusCode < 500) {
            return self::CLIENT_ERROR;
        }

        if ($statusCode >= 500) {
            return self::SERVER_ERROR;
        }

        return self::SUCCESS;
    }
}
