<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_3;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('core')]
class Migration1568645037AddEnqueueDbal extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1568645037;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(
            'CREATE TABLE `enqueue` (
               `id` char(36) COLLATE utf8_unicode_ci NOT NULL COMMENT \'(DC2Type:guid)\',
               `published_at` bigint(20) NOT NULL,
               `body` longtext COLLATE utf8_unicode_ci DEFAULT NULL,
               `headers` longtext COLLATE utf8_unicode_ci DEFAULT NULL,
               `properties` longtext COLLATE utf8_unicode_ci DEFAULT NULL,
               `redelivered` tinyint(1) DEFAULT NULL,
               `queue` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
               `priority` smallint(6) DEFAULT NULL,
               `delayed_until` bigint(20) DEFAULT NULL,
               `time_to_live` bigint(20) DEFAULT NULL,
               `delivery_id` char(36) COLLATE utf8_unicode_ci DEFAULT NULL COMMENT \'(DC2Type:guid)\',
               `redeliver_after` bigint(20) DEFAULT NULL,
               PRIMARY KEY (`id`),
               KEY `IDX_CFC35A6862A6DC27E0D4FDE17FFD7F63121369211A065DF8BF396750` (`priority`,`published_at`,`queue`,`delivery_id`,`delayed_until`,`id`),
               KEY `IDX_CFC35A68AA0BDFF712136921` (`redeliver_after`,`delivery_id`),
               KEY `IDX_CFC35A68E0669C0612136921` (`time_to_live`,`delivery_id`)
             ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;'
        );
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
