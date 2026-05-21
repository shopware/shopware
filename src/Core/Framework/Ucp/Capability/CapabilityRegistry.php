<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Ucp\Capability;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Ucp\DependencyInjection\UcpCapabilityCompilerPass;

/**
 * @experimental stableVersion:v6.8.0 feature:UCP_SERVER
 *
 * Registry of every UCP capability available in this Shopware installation.
 * Populated at compile-time by {@see UcpCapabilityCompilerPass}.
 *
 * @internal
 */
#[Package('framework')]
class CapabilityRegistry
{
    /**
     * @param array<string, UcpCapability> $capabilities
     */
    public function __construct(
        private array $capabilities = [],
    ) {
    }

    public function get(string $name): ?UcpCapability
    {
        return $this->capabilities[$name] ?? null;
    }

    public function has(string $name): bool
    {
        return isset($this->capabilities[$name]);
    }

    /**
     * @return array<string, UcpCapability>
     */
    public function all(): array
    {
        return $this->capabilities;
    }

    /**
     * @return list<string>
     */
    public function names(): array
    {
        return array_keys($this->capabilities);
    }

    /**
     * @return list<UcpCapability>
     */
    public function rootCapabilities(): array
    {
        return array_values(array_filter(
            $this->capabilities,
            static fn (UcpCapability $c): bool => $c->getExtends() === null
        ));
    }

    /**
     * @return list<UcpCapability>
     */
    public function extensions(): array
    {
        return array_values(array_filter(
            $this->capabilities,
            static fn (UcpCapability $c): bool => $c->getExtends() !== null
        ));
    }
}
