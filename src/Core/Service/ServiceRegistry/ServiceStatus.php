<?php declare(strict_types=1);

namespace Shopware\Core\Service\ServiceRegistry;

use Shopware\Core\Framework\Log\Package;

/**
 * The statuses this version acts on from the registry's versioned service list. A status it doesn't know parses to
 * null, so that service is neither installed, updated nor uninstalled.
 *
 * @codeCoverageIgnore
 *
 * @internal
 */
#[Package('framework')]
enum ServiceStatus: string
{
    case ACTIVE = 'active';
    case RETIRED = 'retired';
}
