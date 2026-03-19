<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Schema;

use Shopware\Core\Framework\Log\Package;
use Symfony\Contracts\Cache\CacheInterface;

/**
 * @internal
 */
#[Package('framework')]
class CachedContentSystemDataLoaderTypeResolver extends AbstractContentSystemDataLoaderTypeResolver
{
    final public const CACHE_KEY = 'core_content_system_data_loader_type_map';

    public function __construct(
        private readonly AbstractContentSystemDataLoaderTypeResolver $innerService,
        private readonly CacheInterface $cache,
    ) {
    }

    public function resolve(): ContentSystemDataLoaderTypeMap
    {
        return $this->cache->get(self::CACHE_KEY, fn () => $this->innerService->resolve());
    }
}
