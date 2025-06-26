<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\SystemCheck\Util;

use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('framework')]
class SalesChannelDomainProvider extends AbstractSalesChannelDomainProvider
{
    public function __construct(
        private readonly Connection $connection,
    ) {
    }

    public function fetchSalesChannelDomains(): SalesChannelDomainCollection
    {
        $sql = <<<'SQL'
            SELECT LOWER(HEX(`sales_channel`.`id`)) AS `sales_channel_id`,
                   `sales_channel_domain`.`url` AS `url`
            FROM `sales_channel_domain`
            INNER JOIN `sales_channel` ON `sales_channel_domain`.`sales_channel_id` = `sales_channel`.`id`
            WHERE `sales_channel`.`type_id` = :typeId
            AND `sales_channel`.`active` = :active
            GROUP BY `sales_channel`.`id`
        SQL;

        $result = $this->connection->fetchAllAssociative(
            $sql,
            ['typeId' => Uuid::fromHexToBytes(Defaults::SALES_CHANNEL_TYPE_STOREFRONT), 'active' => 1]
        );

        $collection = array_map(
            fn ($domain) => SalesChannelDomain::create($domain['sales_channel_id'], $domain['url']),
            $result
        );

        return new SalesChannelDomainCollection($collection);
    }
}
