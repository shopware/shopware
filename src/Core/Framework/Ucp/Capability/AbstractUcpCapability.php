<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Ucp\Capability;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Ucp\UcpVersion;

/**
 * @experimental stableVersion:v6.8.0 feature:UCP_SERVER
 *
 * Convenience base class for stock UCP capabilities. Concrete capabilities
 * override the four constants to declare identity and link to the spec.
 */
#[Package('framework')]
abstract class AbstractUcpCapability implements UcpCapability
{
    public function getVersion(): string
    {
        return UcpVersion::CURRENT;
    }

    public function getExtends(): string|array|null
    {
        return null;
    }

    public function getProfileConfig(): ?array
    {
        return null;
    }
}
