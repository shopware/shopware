<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Ucp\Capability;

use Shopware\Core\Framework\Log\Package;

/**
 * @experimental stableVersion:v6.8.0 feature:UCP_SERVER
 *
 * Result value-object holding the negotiated capability set between a
 * business profile and a platform profile.
 *
 * Shape mirrors UCP `ucp.capabilities` response format:
 *
 *   [
 *     "dev.ucp.shopping.cart"     => [ ["version" => "2026-01-23"] ],
 *     "dev.ucp.shopping.checkout" => [ ["version" => "2026-01-23"] ],
 *     ...
 *   ]
 */
#[Package('framework')]
final class CapabilityIntersection
{
    /**
     * @param array<string, array<int, array<string, mixed>>> $capabilities
     */
    public function __construct(
        public readonly array $capabilities,
        public readonly string $protocolVersion,
    ) {
    }

    public function has(string $name): bool
    {
        return isset($this->capabilities[$name]);
    }

    public function isEmpty(): bool
    {
        return $this->capabilities === [];
    }

    /**
     * @return list<string>
     */
    public function names(): array
    {
        return array_keys($this->capabilities);
    }

    /**
     * @return array<string, array<int, array<string, mixed>>>
     */
    public function toArray(): array
    {
        return $this->capabilities;
    }
}
