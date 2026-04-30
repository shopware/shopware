<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Webhook\Telemetry;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
enum WebhookAuditAgeBucket: string
{
    case FIFTEEN_MINUTES = '15m';
    case ONE_HOUR = '1h';
    case TWENTY_FOUR_HOURS = '24h';
}
