<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Search;

use Shopware\Core\Framework\Adapter\Cache\CacheTagCollector;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

/**
 * @final
 *
 * @phpstan-import-type SearchConfig from SearchConfigLoader
 */
#[Package('framework')]
class CachedSearchConfigLoader extends SearchConfigLoader
{
    final public const CACHE_KEY = 'search-config';

    /**
     * @internal
     */
    public function __construct(
        private readonly SearchConfigLoader $decorated,
        private readonly CacheInterface $cache,
        private readonly CacheTagCollector $cacheTagCollector
    ) {
    }

    /**
     * @return array<SearchConfig>
     */
    public function load(Context $context): array
    {
        $this->cacheTagCollector->addTag(self::CACHE_KEY);

        $key = self::CACHE_KEY . '-' . implode('-', $context->getLanguageIdChain());

        return $this->cache->get($key, function (ItemInterface $item) use ($context): array {
            $item->tag(self::CACHE_KEY);

            return $this->decorated->load($context);
        });
    }
}
