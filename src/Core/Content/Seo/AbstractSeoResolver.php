<?php declare(strict_types=1);

namespace Shopware\Core\Content\Seo;

use Shopware\Core\Framework\Log\Package;

/**
 * @phpstan-type ResolvedSeoUrlArray = array{id?: string, pathInfo: string, isCanonical: bool|string, canonicalPathInfo?: string, seoPathInfo?: string}
 */
#[Package('inventory')]
abstract class AbstractSeoResolver
{
    abstract public function getDecorated(): AbstractSeoResolver;

    /**
     * @deprecated tag:v6.8.0 - will be removed in v6.8.0, use {@see resolveUrl()} instead
     *
     * @return ResolvedSeoUrlArray
     */
    abstract public function resolve(string $languageId, string $salesChannelId, string $pathInfo): array;

    /**
     * Default implementation delegates to {@see resolve()} for backward compatibility with existing
     * decorators that only override resolve(). Subclasses should override this method directly to
     * benefit from query-string-aware resolution.
     *
     * In v6.8.0 this method becomes abstract and {@see resolve()} will be removed.
     */
    public function resolveUrl(SeoUrlRequestContext $context): ResolvedSeoUrl
    {
        $data = $this->resolve($context->languageId, $context->salesChannelId, $context->pathInfo);

        return new ResolvedSeoUrl(
            pathInfo: $data['pathInfo'],
            isCanonical: (bool) $data['isCanonical'],
            id: $data['id'] ?? null,
            canonicalPathInfo: $data['canonicalPathInfo'] ?? null,
            seoPathInfo: $data['seoPathInfo'] ?? null,
        );
    }
}
