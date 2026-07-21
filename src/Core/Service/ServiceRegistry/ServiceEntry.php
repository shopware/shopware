<?php declare(strict_types=1);

namespace Shopware\Core\Service\ServiceRegistry;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
readonly class ServiceEntry
{
    public function __construct(
        public string $name,
        public string $description,
        public string $host,
        public string $appEndpoint,
        public bool $activateOnInstall = true,
        /**
         * @deprecated tag:v6.8.0 - Will be removed with the legacy commercial license sync endpoint support. Use the `commercial_license.provided` webhook instead.
         */
        public ?string $licenseSyncEndPoint = null,
    ) {
    }
}
