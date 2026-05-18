<?php declare(strict_types=1);

namespace Shopware\Core\Content\Seo\UrlProvider;

use Shopware\Core\Content\Seo\DTO\SeoUrl;
use Shopware\Core\Framework\Log\Package;

#[Package('discovery')]
interface UrlProviderInterface
{
    /**
     * @param list<string> $ids
     *
     * @return list<SeoUrl>
     */
    public function getSeoUrls(array $ids, UrlType $urlType, string $languageId, string $salesChannelId): array;

    /**
     * @param array<string, mixed> $parameters
     */
    public function generate(UrlType $urlType, array $parameters = []): string;

    /**
     * @param array<string, mixed> $parameters
     */
    public function generateWithPlaceholder(UrlType $urlType, array $parameters = []): string;

    public function getRouteNameByUrlType(UrlType $urlType): string;

    public function getUrlTypeByRouteName(string $routeName): ?UrlType;
}
