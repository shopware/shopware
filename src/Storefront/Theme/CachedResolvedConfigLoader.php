<?php declare(strict_types=1);

namespace Shopware\Storefront\Theme;

use Shopware\Core\Framework\Adapter\Cache\CacheValueCompressor;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

class CachedResolvedConfigLoader extends AbstractResolvedConfigLoader
{
    private AbstractResolvedConfigLoader $decorated;

    private CacheInterface $cache;

    public function __construct(AbstractResolvedConfigLoader $decorated, CacheInterface $cache)
    {
        $this->decorated = $decorated;
        $this->cache = $cache;
    }

    public function getDecorated(): AbstractResolvedConfigLoader
    {
        return $this->decorated;
    }

    public function load(string $themeId, SalesChannelContext $context): array
    {
        $name = self::buildName($themeId);

        $key = md5(implode('-', [$name, $context->getSalesChannelId(), $context->getDomainId()]));

        $value = $this->cache->get($key, function (ItemInterface $item) use ($name, $themeId, $context) {
            $config = $this->getDecorated()->load($themeId, $context);

            $item->tag([$name]);

            return CacheValueCompressor::compress($config);
        });

        return CacheValueCompressor::uncompress($value);
    }

    public static function buildName(string $themeId): string
    {
        return 'theme-config-' . $themeId;
    }
}
