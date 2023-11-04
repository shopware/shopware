<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_4;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('core')]
class Migration1652441129ReplaceIconNames extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1652441129;
    }

    public function update(Connection $connection): void
    {
        $this->replaceSalesChannelTypeIconName('default-building-shop', 'regular-storefront', $connection);
        $this->replaceSalesChannelTypeIconName('default-shopping-basket', 'regular-shopping-basket', $connection);
    }

    public function updateDestructive(Connection $connection): void
    {
    }

    public function replaceSalesChannelTypeIconName(string $oldIconName, string $newIconName, Connection $connection): void
    {
        $queryBuilder = $connection->createQueryBuilder();

        $oldIconSalesChannelTypes = $queryBuilder->select('id')
            ->from('sales_channel_type')
            ->where('icon_name = :iconName')
            ->setParameter('iconName', $oldIconName)
            ->executeQuery()
            ->fetchFirstColumn();

        foreach ($oldIconSalesChannelTypes as $id) {
            $connection->executeStatement(
                'UPDATE `sales_channel_type` SET `icon_name` = :newIconName WHERE `id`= :id',
                [
                    'id' => $id,
                    'newIconName' => $newIconName,
                ]
            );
        }
    }
}
