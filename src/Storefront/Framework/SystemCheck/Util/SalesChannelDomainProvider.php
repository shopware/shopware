<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\SystemCheck\Util;

use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('discovery')]
class SalesChannelDomainProvider extends AbstractSalesChannelDomainProvider
{
    public function __construct(
        private readonly Connection $connection,
    ) {
    }

    public function fetchSalesChannelDomains(): SalesChannelDomainCollection
    {
        // One domain per sales channel, and always the same one: the language and currency below have
        // to describe the domain whose URL is probed, and a check that silently rotates between
        // domains would report a different page from run to run. `MIN(id)` picks it deterministically,
        // which `GROUP BY sales_channel.id` alone does not.
        $sql = <<<'SQL'
            SELECT LOWER(HEX(`sales_channel_domain`.`id`)) AS `id`,
                   LOWER(HEX(`sales_channel_domain`.`sales_channel_id`)) AS `sales_channel_id`,
                   `sales_channel_domain`.`url` AS `url`,
                   LOWER(HEX(`sales_channel_domain`.`language_id`)) AS `language_id`,
                   LOWER(HEX(`sales_channel_domain`.`currency_id`)) AS `currency_id`
            FROM `sales_channel_domain`
            INNER JOIN `sales_channel` ON `sales_channel_domain`.`sales_channel_id` = `sales_channel`.`id`
            INNER JOIN (
                SELECT `sales_channel_id`, MIN(`id`) AS `id`
                FROM `sales_channel_domain`
                GROUP BY `sales_channel_id`
            ) `first_domain`
                ON `first_domain`.`id` = `sales_channel_domain`.`id`
            WHERE `sales_channel`.`type_id` = :typeId
            AND `sales_channel`.`active` = :active
        SQL;

        $result = $this->connection->fetchAllAssociative(
            $sql,
            ['typeId' => Uuid::fromHexToBytes(Defaults::SALES_CHANNEL_TYPE_STOREFRONT), 'active' => 1]
        );

        $collection = array_map(
            static fn ($domain) => SalesChannelDomain::create(
                $domain['sales_channel_id'],
                $domain['url'],
                $domain['id'],
                $domain['language_id'],
                $domain['currency_id'],
            ),
            $result
        );

        return new SalesChannelDomainCollection($collection);
    }
}
