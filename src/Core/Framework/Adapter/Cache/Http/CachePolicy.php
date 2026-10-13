<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Cache\Http;

use Shopware\Core\Framework\Adapter\AdapterException;
use Shopware\Core\Framework\Log\Package;

/**
 * Represents a single HTTP cache policy with cache control directives.
 *
 * @internal
 *
 * @phpstan-import-type CacheControlDirectivesConfig from CacheControlDirectives
 *
 * @phpstan-type CachePolicyConfig array{
 *     headers: array{
 *         cache_control: CacheControlDirectivesConfig,
 *         no_vary_search?: string|null
 *     }
 * }
 */
#[Package('framework')]
readonly class CachePolicy
{
    /**
     * @param string|null $noVarySearch Verbatim value of the `No-Vary-Search` header, e.g. `key-order`
     */
    public function __construct(
        public CacheControlDirectives $cacheControl,
        public ?string $noVarySearch = null,
    ) {
    }

    /**
     * Create from configuration array
     *
     * @param CachePolicyConfig $data
     */
    public static function fromArray(array $data): self
    {
        if (!isset($data['headers']['cache_control'])) {
            throw AdapterException::invalidCachePolicyConfiguration('missing required "headers.cache_control" configuration');
        }

        $cacheControl = CacheControlDirectives::fromArray($data['headers']['cache_control']);

        return new self(
            cacheControl: $cacheControl,
            noVarySearch: $data['headers']['no_vary_search'] ?? null,
        );
    }

    public function with(
        ?CacheControlDirectives $cacheControl = null,
        ?string $noVarySearch = null,
    ): self {
        return new self(
            cacheControl: $cacheControl ?? $this->cacheControl,
            noVarySearch: $noVarySearch ?? $this->noVarySearch,
        );
    }

    /**
     * Fallback no-store policy when policy cannot be resolved or NO_STORE is enforced.
     */
    public static function noStore(): self
    {
        return new self(
            cacheControl: new CacheControlDirectives(
                noStore: true,
                noCache: true,
                mustRevalidate: true,
                maxAge: 0,
            )
        );
    }
}
