<?php declare(strict_types=1);

namespace Shopware\Core\Content\Seo;

use Shopware\Core\Framework\Log\Package;

/**
 * @phpstan-type ResolvedSeoUrl = array{id?: string, pathInfo: string, isCanonical: bool|string, canonicalPathInfo?: string, seoPathInfo?: string}
 */
#[Package('inventory')]
abstract class AbstractSeoResolver
{
    abstract public function getDecorated(): AbstractSeoResolver;

    /**
     * @deprecated tag:v6.8.0 - reason:new-optional-parameter - parameter $queryString will be added
     *
     * @return ResolvedSeoUrl
     */
    abstract public function resolve(string $languageId, string $salesChannelId, string $pathInfo /* , string $queryString = null */): array;
}
