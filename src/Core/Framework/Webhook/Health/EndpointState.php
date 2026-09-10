<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Webhook\Health;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
enum EndpointState: string
{
    case Healthy = 'healthy';
    case Degraded = 'degraded';
    case Suspended = 'suspended';
    case Disabled = 'disabled';
}
