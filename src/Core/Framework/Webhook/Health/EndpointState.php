<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Webhook\Health;

use Shopware\Core\Framework\Log\Package;

/**
 * Per-webhook health state. Stored on the internal `webhook_health` table (not the DAL `webhook` entity).
 *
 * @internal
 */
#[Package('framework')]
enum EndpointState: string
{
    case Healthy = 'healthy';
    case Degraded = 'degraded';
    case Suspended = 'suspended';
    case Disabled = 'disabled';
}
