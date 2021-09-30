<?php declare(strict_types=1);

namespace Shopware\Core\Content\Sitemap\Provider;

use Doctrine\DBAL\Connection;
use Shopware\Core\Content\Sitemap\Struct\UrlResult;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

abstract class AbstractUrlProvider implements UrlProviderInterface
{
    /**
     * This function should return the decorated core service.
     * This ensures that when new functions are implemented in this class, decorations will continue to work
     */
    abstract public function getDecorated(): AbstractUrlProvider;

    abstract public function getName(): string;

    abstract public function getUrls(SalesChannelContext $context, int $limit, ?int $offset = null): UrlResult;

    protected function getSeoUrls(array $ids, string $routeName, SalesChannelContext $context, Connection $connection): array
    {
        $sql = 'SELECT LOWER(HEX(foreign_key)) as foreign_key, seo_path_info
                    FROM seo_url WHERE foreign_key IN (:ids)
                     AND `seo_url`.`route_name` =:routeName
                     AND `seo_url`.`is_canonical` = 1
                     AND `seo_url`.`is_deleted` = 0
                     AND `seo_url`.`language_id` =:languageId
                     AND (`seo_url`.`sales_channel_id` =:salesChannelId OR seo_url.sales_channel_id IS NULL)';

        return $connection->fetchAll(
            $sql,
            [
                'routeName' => $routeName,
                'languageId' => Uuid::fromHexToBytes($context->getLanguageId()),
                'salesChannelId' => Uuid::fromHexToBytes($context->getSalesChannelId()),
                'ids' => Uuid::fromHexToBytesList(array_values($ids)),
            ],
            [
                'ids' => Connection::PARAM_STR_ARRAY,
            ]
        );
    }
}
