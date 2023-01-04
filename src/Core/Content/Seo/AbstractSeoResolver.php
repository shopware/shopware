<?php declare(strict_types=1);

namespace Shopware\Core\Content\Seo;

use Shopware\Core\Framework\Log\Package;

/**
 * @phpstan-type ResolvedSeoUrl = array{id?: string, pathInfo: string, isCanonical: bool|string, canonicalPathInfo?: string}
 */
#[Package('sales-channel')]
abstract class AbstractSeoResolver
{
    abstract public function getDecorated(): AbstractSeoResolver;

    /**
     * @return ResolvedSeoUrl
     */
    abstract public function resolve(string $languageId, string $salesChannelId, string $pathInfo): array;
}
