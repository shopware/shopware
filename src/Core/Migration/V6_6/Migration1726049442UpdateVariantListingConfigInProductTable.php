<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_6;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('core')]
class Migration1726049442UpdateVariantListingConfigInProductTable extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1726049442;
    }

    public function update(Connection $connection): void
    {
        $productIds = $connection->fetchFirstColumn('
            SELECT `id`
            FROM `product` as `parent`
            WHERE `parent_id` IS NULL
              AND `variant_listing_config` IS NOT NULL
              AND JSON_UNQUOTE(JSON_EXTRACT(`variant_listing_config`, "$.displayParent")) = "0"
              AND JSON_UNQUOTE(JSON_EXTRACT(`parent`.`variant_listing_config`, "$.mainVariantId")) IS NOT NULL
              AND JSON_UNQUOTE(JSON_EXTRACT(`parent`.`variant_listing_config`, "$.mainVariantId")) NOT IN
                (SELECT LOWER(HEX(`id`)) FROM `product` WHERE `parent_id` = `parent`.`id`)
        ');

        if (empty($productIds)) {
            return;
        }

        $connection->executeStatement(
            'UPDATE `product` SET `variant_listing_config` = NULL, `display_group` = NULL WHERE `id` IN (:ids)',
            ['ids' => $productIds],
            ['ids' => ArrayParameterType::STRING]
        );

        $connection->executeStatement(
            'UPDATE `product` SET `display_group` = MD5(HEX(`parent_id`)) WHERE `parent_id` IN (:ids)',
            ['ids' => $productIds],
            ['ids' => ArrayParameterType::STRING]
        );
    }
}
