<?php declare(strict_types=1);

namespace Shopware\Storefront\Theme;

use Shopware\Core\Framework\Log\Package;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

/**
 * Caches compiled SCSS output by decorating an inner {@see AbstractScssCompiler}.
 * The cache is engaged per-call via `useCache: true` on the {@see CompilerConfiguration};
 * any other value (or absent key) bypasses the cache entirely and delegates to the inner compiler.
 *
 * @internal
 */
#[Package('framework')]
class CachedScssCompiler extends AbstractScssCompiler
{
    /**
     * @param array{lifetime?: positive-int, tags?: list<string>} $cacheOptions Pass `lifetime` (TTL in seconds) and/or `tags`
     *                                                                          (cache invalidation tags) to control how cache
     *                                                                          entries are stored.
     */
    public function __construct(
        private readonly AbstractScssCompiler $inner,
        private readonly TagAwareCacheInterface $cache,
        private readonly ScssCacheKeyGenerator $cacheKeyGenerator,
        private readonly array $cacheOptions = [],
    ) {
    }

    public function compileString(AbstractCompilerConfiguration $config, string $scss, ?string $path = null): string
    {
        if ($config->getValue('useCache') !== true) {
            return $this->inner->compileString($config, $scss, $path);
        }

        $cacheKey = $this->cacheKeyGenerator->generate(
            $scss,
            $path,
            $this->resolveOutputStyle($config),
            $this->resolveImportPaths($config),
        );

        return $this->cache->get($cacheKey, function (ItemInterface $item) use ($config, $scss, $path) {
            $this->configureCacheItem($item, $this->cacheOptions);

            return $this->inner->compileString($config, $scss, $path);
        });
    }

    /**
     * @param array{lifetime?: positive-int, tags?: list<string>} $cacheOptions
     */
    private function configureCacheItem(ItemInterface $item, array $cacheOptions): void
    {
        $lifetime = $cacheOptions['lifetime'] ?? null;
        if (\is_int($lifetime) && $lifetime > 0) {
            $item->expiresAfter($lifetime);
        }

        $tags = $cacheOptions['tags'] ?? null;
        if (\is_array($tags)) {
            $item->tag($tags);
        }
    }

    /**
     * Normalises the configured output style to its string form. scssphp exposes `OutputStyle`
     * as a string-constant class (1.x) or a backed enum (2.x); both are reduced to a plain string
     * so the value can flow into the cache key.
     */
    private function resolveOutputStyle(AbstractCompilerConfiguration $config): string
    {
        $outputStyle = $config->getValue('outputStyle');

        if (\is_string($outputStyle)) {
            return $outputStyle;
        }

        if ($outputStyle instanceof \BackedEnum) {
            return (string) $outputStyle->value;
        }

        // Plain string default (the value of OutputStyle::COMPRESSED on both scssphp 1.x and 2.x);
        // kept as a literal so this class needs no change when scssphp turns OutputStyle into an enum.
        return 'compressed';
    }

    /**
     * @return array<int|string, mixed>
     */
    private function resolveImportPaths(AbstractCompilerConfiguration $config): array
    {
        $importPaths = $config->getValue('importPaths');

        return \is_array($importPaths) ? $importPaths : [];
    }
}
