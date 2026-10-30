<?php declare(strict_types=1);

namespace Shopware\Core\Service\ServiceRegistry;

use Shopware\Core\Framework\Log\Package;

/**
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
