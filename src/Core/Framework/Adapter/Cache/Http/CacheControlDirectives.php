<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Cache\Http;

use Shopware\Core\Framework\Log\Package;

/**
 * Represents Cache-Control header directives.
 * Follows the structure of Symfony's Response::setCache() options.
 *
 * @internal
 *
 * @phpstan-type CacheControlDirectivesConfig array{
 *     public?: bool,
 *     private?: bool,
 *     no_cache?: bool,
 *     no_store?: bool,
 *     no_transform?: bool,
 *     must_revalidate?: bool,
 *     proxy_revalidate?: bool,
 *     immutable?: bool,
 *     max_age?: int,
 *     s_maxage?: int,
 *     stale_while_revalidate?: int,
 *     stale_if_error?: int
 * }
 */
#[Package('framework')]
readonly class CacheControlDirectives
{
    public function __construct(
        public ?bool $public = null,
        public ?bool $private = null,
        public ?bool $noCache = null,
        public ?bool $noStore = null,
        public ?bool $noTransform = null,
        public ?bool $mustRevalidate = null,
        public ?bool $proxyRevalidate = null,
        public ?bool $immutable = null,
        public ?int $maxAge = null,
        public ?int $sMaxAge = null,
        public ?int $staleWhileRevalidate = null,
        public ?int $staleIfError = null,
    ) {
    }

    /**
     * Convert directives to array format suitable for Response::setCache()
     *
     * @return CacheControlDirectivesConfig
     */
    public function toArray(): array
    {
        return array_filter([
            'public' => $this->public,
            'private' => $this->private,
            'no_cache' => $this->noCache,
            'no_store' => $this->noStore,
            'no_transform' => $this->noTransform,
            'must_revalidate' => $this->mustRevalidate,
            'proxy_revalidate' => $this->proxyRevalidate,
            'immutable' => $this->immutable,
            'max_age' => $this->maxAge,
            's_maxage' => $this->sMaxAge,
            'stale_while_revalidate' => $this->staleWhileRevalidate,
            'stale_if_error' => $this->staleIfError,
        ], fn ($value) => $value !== null);
    }

    /**
     * Create from configuration array
     *
     * @param CacheControlDirectivesConfig $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            public: isset($data['public']) ? (bool) $data['public'] : null,
            private: isset($data['private']) ? (bool) $data['private'] : null,
            noCache: isset($data['no_cache']) ? (bool) $data['no_cache'] : null,
            noStore: isset($data['no_store']) ? (bool) $data['no_store'] : null,
            noTransform: isset($data['no_transform']) ? (bool) $data['no_transform'] : null,
            mustRevalidate: isset($data['must_revalidate']) ? (bool) $data['must_revalidate'] : null,
            proxyRevalidate: isset($data['proxy_revalidate']) ? (bool) $data['proxy_revalidate'] : null,
            immutable: isset($data['immutable']) ? (bool) $data['immutable'] : null,
            maxAge: isset($data['max_age']) ? (int) $data['max_age'] : null,
            sMaxAge: isset($data['s_maxage']) ? (int) $data['s_maxage'] : null,
            staleWhileRevalidate: isset($data['stale_while_revalidate']) ? (int) $data['stale_while_revalidate'] : null,
            staleIfError: isset($data['stale_if_error']) ? (int) $data['stale_if_error'] : null,
        );
    }

    /**
     * Create a new CacheControlDirectives with overridden values
     *
     * @param CacheControlDirectivesConfig $overrides
     */
    public function with(array $overrides): self
    {
        return self::fromArray(array_merge($this->toArray(), $overrides));
    }
}
