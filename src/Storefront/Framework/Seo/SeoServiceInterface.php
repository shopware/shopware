<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Seo;

use Shopware\Core\Framework\DataAbstractionLayer\Entity;

interface SeoServiceInterface
{
    public function getSeoUrlContext(string $routeName, Entity $entity): array;

    public function generateSeoUrls(string $salesChannelId, string $routeName, array $ids, ?string $templateString = null): iterable;

    public function updateSeoUrls(string $salesChannelId, string $routeName, array $foreignKeys, iterable $seoUrls): void;
}
