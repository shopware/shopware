<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Webhook\Telemetry;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
enum WebhookMetricLabel: string
{
    case AGE_BUCKET = 'age_bucket';
    case KIND = 'kind';
    case OUTCOME = 'outcome';
    case REASON = 'reason';
    case STATUS = 'status';
}
