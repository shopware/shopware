<?php declare(strict_types=1);

namespace Shopware\Core\Content\Seo;

use Shopware\Core\Framework\Adapter\Cache\CacheValueCompressor;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

/**
 * @deprecated tag:v6.6.0 - Will be removed without a replacement
 *
 * @phpstan-import-type ResolvedSeoUrl from AbstractSeoResolver
 */
#[Package('sales-channel')]
class CachedSeoResolver extends AbstractSeoResolver
{
    /**
     * @internal
     */
    public function __construct(
        private readonly AbstractSeoResolver $decorated,
        private readonly CacheInterface $cache
    ) {
    }

    public function getDecorated(): AbstractSeoResolver
    {
        Feature::triggerDeprecationOrThrow('v6.6.0.0', Feature::deprecatedClassMessage(self::class, 'v6.6.0.0'));

        return $this->decorated;
    }

    /**
     * @return ResolvedSeoUrl
     */
    public function resolve(string $languageId, string $salesChannelId, string $pathInfo): array
    {
        Feature::triggerDeprecationOrThrow('v6.6.0.0', Feature::deprecatedClassMessage(self::class, 'v6.6.0.0'));

        $key = 'seo-resolver-' . md5(implode('-', [$languageId, $salesChannelId, $pathInfo]));

        $value = $this->cache->get($key, function (ItemInterface $item) use ($languageId, $salesChannelId, $pathInfo) {
            $resolved = $this->getDecorated()->resolve($languageId, $salesChannelId, $pathInfo);

            $item->tag([self::buildName($pathInfo)]);

            return CacheValueCompressor::compress($resolved);
        });

        /** @var ResolvedSeoUrl $value */
        $value = CacheValueCompressor::uncompress($value);

        return $value;
    }

    public static function buildName(string $pathInfo): string
    {
        Feature::triggerDeprecationOrThrow('v6.6.0.0', Feature::deprecatedClassMessage(self::class, 'v6.6.0.0'));

        return 'path-info-' . md5($pathInfo);
    }
}
