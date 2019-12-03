<?php declare(strict_types=1);

namespace Shopware\Core\Content\Seo;

interface SeoResolverInterface
{
    public function resolveSeoPath(string $languageId, string $salesChannelId, string $pathInfo): array;
}
