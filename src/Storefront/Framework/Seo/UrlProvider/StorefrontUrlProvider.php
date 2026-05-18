<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Seo\UrlProvider;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Shopware\Core\Content\Seo\DTO\SeoUrl;
use Shopware\Core\Content\Seo\SeoUrlPlaceholderHandlerInterface;
use Shopware\Core\Content\Seo\UrlProvider\UrlProviderInterface;
use Shopware\Core\Content\Seo\UrlProvider\UrlType;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Storefront\Framework\Seo\SeoUrlRoute\LandingPageSeoUrlRoute;
use Shopware\Storefront\Framework\Seo\SeoUrlRoute\NavigationPageSeoUrlRoute;
use Shopware\Storefront\Framework\Seo\SeoUrlRoute\ProductPageSeoUrlRoute;
use Symfony\Component\Routing\RouterInterface;

#[Package('discovery')]
class StorefrontUrlProvider implements UrlProviderInterface
{
    private const HOME_PAGE_ROUTE = 'frontend.home.page';

    /**
     * @internal
     */
    public function __construct(
        private readonly SeoUrlPlaceholderHandlerInterface $seoUrlReplacer,
        private readonly RouterInterface $router,
        private readonly Connection $connection,
    ) {
    }

    /**
     * {@inheritDoc}
     */
    public function getSeoUrls(array $ids, UrlType $urlType, string $languageId, string $salesChannelId): array
    {
        $sql = 'SELECT LOWER(HEX(`seo_url`.`foreign_key`)) as foreignKey,
                    `seo_url`.`seo_path_info` as seoPathInfo,
                    `seo_url`.`path_info` as pathInfo,
                    LOWER(HEX(`seo_url`.`id`)) as id
                FROM `seo_url`
                WHERE `seo_url`.`foreign_key` IN (:ids)
                AND `seo_url`.`route_name` = :routeName
                AND `seo_url`.`is_canonical` = 1
                AND `seo_url`.`is_deleted` = 0
                AND `seo_url`.`language_id` = :languageId
                AND (`seo_url`.`sales_channel_id` = :salesChannelId OR `seo_url`.`sales_channel_id` IS NULL)';

        /** @var list<array{foreignKey: string, seoPathInfo: string, pathInfo: string, id: string}> $result */
        $result = $this->connection->fetchAllAssociative(
            $sql,
            [
                'routeName' => $this->getRouteNameByUrlType($urlType),
                'languageId' => Uuid::fromHexToBytes($languageId),
                'salesChannelId' => Uuid::fromHexToBytes($salesChannelId),
                'ids' => Uuid::fromHexToBytesList(array_values($ids)),
            ],
            [
                'ids' => ArrayParameterType::BINARY,
            ]
        );

        return array_map(
            static fn (array $row) => new SeoUrl(...$row),
            $result
        );
    }

    /**
     * {@inheritDoc}
     */
    public function generate(UrlType $urlType, array $parameters = []): string
    {
        return $this->router->generate($this->getRouteNameByUrlType($urlType), $parameters);
    }

    /**
     * {@inheritDoc}
     */
    public function generateWithPlaceholder(UrlType $urlType, array $parameters = []): string
    {
        return $this->seoUrlReplacer->generate($this->getRouteNameByUrlType($urlType), $parameters);
    }

    public function getRouteNameByUrlType(UrlType $urlType): string
    {
        return match ($urlType) {
            UrlType::HOME => self::HOME_PAGE_ROUTE,
            UrlType::PRODUCT => ProductPageSeoUrlRoute::ROUTE_NAME,
            UrlType::CATEGORY => NavigationPageSeoUrlRoute::ROUTE_NAME,
            UrlType::LANDING_PAGE => LandingPageSeoUrlRoute::ROUTE_NAME,
        };
    }

    public function getUrlTypeByRouteName(string $routeName): ?UrlType
    {
        return match ($routeName) {
            self::HOME_PAGE_ROUTE => UrlType::HOME,
            ProductPageSeoUrlRoute::ROUTE_NAME => UrlType::PRODUCT,
            NavigationPageSeoUrlRoute::ROUTE_NAME => UrlType::CATEGORY,
            LandingPageSeoUrlRoute::ROUTE_NAME => UrlType::LANDING_PAGE,
            default => null,
        };
    }
}
