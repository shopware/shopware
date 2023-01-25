<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_4;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityDefinitionQueryHelper;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('core')]
class Migration1656397126AddMainVariantConfiguration extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1656397126;
    }

    public function update(Connection $connection): void
    {
        if (!EntityDefinitionQueryHelper::columnExists($connection, 'product', 'display_parent')) {
            $connection->executeStatement(
                'ALTER TABLE `product` ADD COLUMN `display_parent` TINYINT(1) NULL DEFAULT NULL'
            );
        }

        if (!EntityDefinitionQueryHelper::columnExists($connection, 'product', 'variant_listing_config')) {
            $connection->executeStatement(
                'ALTER TABLE `product` ADD COLUMN `variant_listing_config` JSON
                        GENERATED ALWAYS AS (CASE WHEN `display_parent` IS NOT NULL OR `main_variant_id` IS NOT NULL OR `configurator_group_config` IS NOT NULL
                            THEN (JSON_OBJECT( \'displayParent\', `display_parent`, \'mainVariantId\', LOWER(HEX(`main_variant_id`)) ,\'configuratorGroupConfig\', JSON_EXTRACT(`configurator_group_config`, \'$\')))
                        END) VIRTUAL'
            );
        }
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
