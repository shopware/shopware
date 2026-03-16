<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Hydration\DataLoader;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;

/**
 * Result object from content data loaders.
 *
 * Contains loaded data and cache tag information:
 * - cacheTags = null  → Loader is not cache-aware, page must not be cached
 * - cacheTags = []    → Cache-aware, no tags needed (e.g., data already tagged elsewhere)
 * - cacheTags = [...] → Cache-aware with specific invalidation tags
 *
 * @template TData of Struct
 */
#[Package('framework')]
final class ContentDataLoaderResult
{
    /**
     * @param TData|null $data
     * @param list<string>|null $cacheTags
     */
    private function __construct(
        public readonly ?Struct $data,
        private readonly ?array $cacheTags = null,
    ) {
    }

    /**
     * Data not found, but lookup was cache-aware.
     * Page remains cacheable, no tags added.
     *
     * @return self<Struct>
     */
    public static function notFound(): self
    {
        return new self(data: null, cacheTags: []);
    }

    /**
     * Data loaded but cannot be cache-tracked.
     * Page will not be cached.
     *
     * @template T of Struct
     *
     * @param T $data
     *
     * @return self<T>
     */
    public static function uncacheable(Struct $data): self
    {
        return new self(data: $data, cacheTags: null);
    }

    /**
     * Data loaded and cache-trackable with specific tags.
     *
     * @template T of Struct
     *
     * @param T $data
     *
     * @return self<T>
     */
    public static function cached(Struct $data, string ...$tags): self
    {
        return new self(data: $data, cacheTags: array_values($tags));
    }

    /**
     * Data loaded, cache-aware, but tags already collected elsewhere.
     * Use when delegating to routes that handle their own cache tags.
     *
     * @template T of Struct
     *
     * @param T $data
     *
     * @return self<T>
     */
    public static function cachedExternally(Struct $data): self
    {
        return new self(data: $data, cacheTags: []);
    }

    public function hasData(): bool
    {
        return $this->data !== null;
    }

    public function isCacheAware(): bool
    {
        return $this->cacheTags !== null;
    }

    /**
     * @return list<string>
     */
    public function getCacheTags(): array
    {
        return $this->cacheTags ?? [];
    }
}
