<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Cache\Http;

use Shopware\Core\Framework\Log\Package;

/**
 * Represents default cache policies configuration for an area (e.g., storefront, store_api)
 *
 * @internal
 *
 * @phpstan-type DefaultPoliciesConfig array{
 *     cacheable?: string|null,
 *     uncacheable?: string|null
 * }
 */
#[Package('framework')]
readonly class DefaultPolicies
{
    public function __construct(
        public ?string $cacheablePolicyName = null,
        public ?string $uncacheablePolicyName = null,
    ) {
    }

    /**
     * @param DefaultPoliciesConfig $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            cacheablePolicyName: $data['cacheable'] ?? null,
            uncacheablePolicyName: $data['uncacheable'] ?? null,
        );
    }
}
