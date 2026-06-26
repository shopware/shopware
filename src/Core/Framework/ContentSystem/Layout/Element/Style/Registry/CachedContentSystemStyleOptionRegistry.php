<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Layout\Element\Style\Registry;

use Shopware\Core\Framework\ContentSystem\Layout\Element\Style\Specification\StyleOptionSpecification;
use Shopware\Core\Framework\Log\Package;
use Symfony\Contracts\Cache\CacheInterface;

/**
 * @internal
 *
 * @final
 */
#[Package('framework')]
class CachedContentSystemStyleOptionRegistry extends AbstractContentSystemStyleOptionRegistry
{
    private const CACHE_KEY = 'content_system.style_options';

    private const RESOLVED_CACHE_KEY = 'content_system.style_options.resolved';

    public function __construct(
        private readonly AbstractContentSystemStyleOptionRegistry $inner,
        private readonly CacheInterface $cache,
    ) {
    }

    public function getDecorated(): AbstractContentSystemStyleOptionRegistry
    {
        return $this->inner;
    }

    /**
     * @return array<string, StyleOptionSpecification>
     */
    public function all(): array
    {
        return $this->cache->get(self::CACHE_KEY, fn () => $this->inner->all());
    }

    /**
     * @return array<string, StyleOptionSpecification>
     */
    public function allResolved(): array
    {
        return $this->cache->get(self::RESOLVED_CACHE_KEY, fn () => $this->inner->allResolved());
    }

    public function invalidate(): void
    {
        $this->cache->delete(self::CACHE_KEY);
        $this->cache->delete(self::RESOLVED_CACHE_KEY);
    }
}
