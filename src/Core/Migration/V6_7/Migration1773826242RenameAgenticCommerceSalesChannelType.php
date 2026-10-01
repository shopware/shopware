<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('framework')]
class Migration1773826242RenameAgenticCommerceSalesChannelType extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1773826242;
    }

    public function update(Connection $connection): void
    {
        $salesChannelTypeId = Uuid::fromHexToBytes(Defaults::SALES_CHANNEL_TYPE_AGENTIC_COMMERCE);

        $connection->executeStatement(
            'UPDATE `sales_channel_type_translation`
                SET `name` = :name,
                    `manufacturer` = :manufacturer,
                    `description` = :description
             WHERE `sales_channel_type_id` = :salesChannelTypeId',
            [
                'name' => 'Agentic Commerce',
                'manufacturer' => 'shopware AG',
                'description' => 'Sales channel for agentic commerce platforms',
                'salesChannelTypeId' => $salesChannelTypeId,
            ],
        );

        $connection->executeStatement(
            'UPDATE `sales_channel_type_translation` sctt
                INNER JOIN `language` l
                    ON l.id = sctt.language_id
                INNER JOIN `locale` loc
                    ON loc.id = l.locale_id
                SET sctt.name = :name,
                    sctt.manufacturer = :manufacturer,
                    sctt.description = :description
             WHERE sctt.sales_channel_type_id = :salesChannelTypeId
               AND loc.code = :localeCode',
            [
                'name' => 'Agentic Commerce',
                'manufacturer' => 'shopware AG',
                'description' => 'Verkaufskanal für Agentic-Commerce-Plattformen',
                'salesChannelTypeId' => $salesChannelTypeId,
                'localeCode' => 'de-DE',
            ],
        );
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
