<?php declare(strict_types=1);

namespace Shopware\Core\Content\Seo;

/**
 * @phpstan-type ResolvedSeoUrl = array{id?: string, pathInfo: string, isCanonical: bool|string, canonicalPathInfo?: string}
 */
abstract class AbstractSeoResolver
{
    abstract public function getDecorated(): AbstractSeoResolver;

    /**
     * @return ResolvedSeoUrl
     */
    abstract public function resolve(string $languageId, string $salesChannelId, string $pathInfo): array;
}
