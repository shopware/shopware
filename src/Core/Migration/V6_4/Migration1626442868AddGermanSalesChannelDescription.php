<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_4;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('core')]
class Migration1626442868AddGermanSalesChannelDescription extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1626442868;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('
            UPDATE `sales_channel_type_translation`
            INNER JOIN `language`
            ON `language`.id = sales_channel_type_translation.language_id
            SET sales_channel_type_translation.description = "Verkaufskanal mit HTML-Storefront"
            WHERE `language`.name = "Deutsch"
            AND sales_channel_type_translation.name = "Storefront"
            AND sales_channel_type_translation.manufacturer = "shopware AG"
            AND sales_channel_type_translation.description = "Sales channel mit HTML storefront"
        ');

        $connection->executeStatement('
            UPDATE `sales_channel_type_translation`
            INNER JOIN `language`
            ON `language`.id = sales_channel_type_translation.language_id
            SET sales_channel_type_translation.description = "Verkaufskanal mit API-only-Zugang"
            WHERE `language`.name = "Deutsch" AND sales_channel_type_translation.name = "Headless"
            AND sales_channel_type_translation.manufacturer = "shopware AG"
            AND sales_channel_type_translation.description = "API only sales channel"
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
