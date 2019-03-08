<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Seo;

interface SeoServiceInterface
{
    public function generateSeoUrls(string $salesChannelId, string $routeName, array $ids, ?string $templateString = null): iterable;

    public function updateSeoUrls(string $salesChannelId, string $routeName, array $foreignKeys, iterable $seoUrls): void;
}
