<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Cache\Http;

use Shopware\Core\Framework\Log\Package;

/**
 * Value object for default policies per area
 *
 * @internal
 */
#[Package('framework')]
readonly class AreaDefaultPolicies
{
    public function __construct(
        public ?string $cacheablePolicyName = null,
        public ?string $uncacheablePolicyName = null,
    ) {
    }

    /**
     * @param array<string, string|null> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            cacheablePolicyName: $data['cacheable'] ?? null,
            uncacheablePolicyName: $data['uncacheable'] ?? null,
        );
    }
}
