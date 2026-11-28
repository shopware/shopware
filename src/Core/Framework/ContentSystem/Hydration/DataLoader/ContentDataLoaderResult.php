<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Hydration\DataLoader;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;

/**
 * The data a loader returns for one requirement, and the cache dependency of that data.
 * Each factory names one fact about how the loader reached its result; the cache dependency follows from it.
 *
 * Cache tag semantics:
 * - cacheTags = null  → not cache-aware, the render must not be cached
 * - cacheTags = []    → cache-aware, the loader records no tag of its own
 * - cacheTags = [...] → cache-aware, these tags cover the result
 */
#[Package('framework')]
final class ContentDataLoaderResult
{
    /**
     * @param list<string>|null $cacheTags
     */
    private function __construct(
        public readonly ?Struct $data,
        private readonly ?array $cacheTags = null,
    ) {
    }

    /**
     * The loader read nothing outside the stored layout, and that read produced no value, so it records no tag.
     * The layout tags of the render cover the result.
     */
    public static function noInput(): self
    {
        return new self(data: null, cacheTags: []);
    }

    /**
     * The loader read nothing outside the stored layout, and the value depends only on that layout, so it
     * records no tag. The layout tags of the render cover the result.
     */
    public static function layoutOnly(Struct $data): self
    {
        return new self(data: $data, cacheTags: []);
    }

    /**
     * The loader's own lookup produced no data, either because it found nothing or because it threw a
     * `ShopwareHttpException` that the loader caught.
     * The given tags cover the requested data, so a later change of that data invalidates the empty result.
     */
    public static function foundNothing(string $tag, string ...$tags): self
    {
        return new self(data: null, cacheTags: [$tag, ...array_values($tags)]);
    }

    /**
     * The loader's own lookup produced no data, either because it found nothing or because it threw a
     * `ShopwareHttpException` that the loader caught, and no tag covers the request.
     * No tag covers the empty result, so the render must not be cached.
     */
    public static function foundNothingUncacheable(): self
    {
        return new self(data: null, cacheTags: null);
    }

    /**
     * A delegate, such as a Store API route or a platform loader, threw a `ShopwareHttpException`.
     * The delegate owns the caching of that outcome, so the loader records no tag.
     */
    public static function failedExternally(): self
    {
        return new self(data: null, cacheTags: []);
    }

    /**
     * The loader loaded the given data, and no tag covers it, so the render must not be cached.
     */
    public static function uncacheable(Struct $data): self
    {
        return new self(data: $data, cacheTags: null);
    }

    /**
     * The loader read its own data, and the given tags cover it.
     */
    public static function cached(Struct $data, string $tag, string ...$tags): self
    {
        return new self(data: $data, cacheTags: [$tag, ...array_values($tags)]);
    }

    /**
     * A delegate, such as a Store API route, provided the data and owns its caching, so the loader records no tag.
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
