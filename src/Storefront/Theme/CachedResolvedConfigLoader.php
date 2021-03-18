<?php declare(strict_types=1);

namespace Shopware\Storefront\Theme;

use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Adapter\Cache\CacheCompressor;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\Cache\Adapter\TagAwareAdapterInterface;

class CachedResolvedConfigLoader extends AbstractResolvedConfigLoader
{
    private AbstractResolvedConfigLoader $decorated;

    private TagAwareAdapterInterface $cache;

    private LoggerInterface $logger;

    public function __construct(AbstractResolvedConfigLoader $decorated, TagAwareAdapterInterface $cache, LoggerInterface $logger)
    {
        $this->decorated = $decorated;
        $this->cache = $cache;
        $this->logger = $logger;
    }

    public function getDecorated(): AbstractResolvedConfigLoader
    {
        return $this->decorated;
    }

    public function load(string $themeId, SalesChannelContext $context): array
    {
        $name = self::buildName($themeId);

        $key = md5(implode('-', [
            $name,
            $context->getSalesChannelId(),
            $context->getDomainId(),
        ]));

        $item = $this->cache->getItem($key);

        try {
            if ($item->isHit() && $item->get()) {
                $this->logger->info('cache-hit: ' . $name);

                return CacheCompressor::uncompress($item);
            }
        } catch (\Throwable $e) {
            $this->logger->error($e->getMessage());
        }

        $this->logger->info('cache-miss: ' . $name);

        $config = $this->getDecorated()->load($themeId, $context);

        $item = CacheCompressor::compress($item, $config);
        $item->tag([$name]);

        $this->cache->save($item);

        return $config;
    }

    public static function buildName(string $themeId): string
    {
        return 'theme-config-' . $themeId;
    }
}
