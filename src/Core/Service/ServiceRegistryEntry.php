<?php declare(strict_types=1);

namespace Shopware\Core\Service;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('core')]
readonly class ServiceRegistryEntry
{
    public function __construct(public string $name, public string $description, public string $host, public string $appEndpoint, public bool $activateOnInstall = true)
    {
    }
}
