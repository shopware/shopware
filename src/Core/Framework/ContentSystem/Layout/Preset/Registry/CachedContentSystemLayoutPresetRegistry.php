<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Layout\Preset\Registry;

use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Layout\Preset\LayoutPreset;
use Shopware\Core\Framework\Log\Package;
use Symfony\Contracts\Cache\CacheInterface;

/**
 * @internal
 *
 * @final
 */
#[Package('framework')]
class CachedContentSystemLayoutPresetRegistry extends AbstractContentSystemLayoutPresetRegistry
{
    private const CACHE_KEY = 'content_system.layout_presets';

    public function __construct(
        private readonly AbstractContentSystemLayoutPresetRegistry $inner,
        private readonly CacheInterface $cache,
    ) {
    }

    public function getDecorated(): AbstractContentSystemLayoutPresetRegistry
    {
        return $this->inner;
    }

    public function all(): array
    {
        return $this->cache->get(self::CACHE_KEY, fn () => $this->inner->all());
    }

    public function has(string $id): bool
    {
        return \array_key_exists($id, $this->all());
    }

    public function get(string $id): LayoutPreset
    {
        return $this->all()[$id] ?? throw ContentSystemException::layoutPresetNotFound($id);
    }

    public function invalidate(): void
    {
        $this->cache->delete(self::CACHE_KEY);
    }
}
