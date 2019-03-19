<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Struct\Uuid;

class Migration1552991371CustomerGroupDeleteUnusedColumns extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1552991371;
    }

    public function update(Connection $connection): void
    {
        $connection->insert('customer_group_translation', [
            'customer_group_id' => Uuid::fromHexToBytes(Defaults::FALLBACK_CUSTOMER_GROUP),
            'language_id' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM_DE),
            'name' => 'Standard-Kundengruppe',
            'created_at' => date(Defaults::DATE_FORMAT),
        ]);
    }

    public function updateDestructive(Connection $connection): void
    {
        $connection->executeQuery('
            DROP TABLE `customer_group_discount`;
        ');

        $connection->executeQuery('
            ALTER TABLE `customer_group`
            DROP `input_gross`,
            DROP `has_global_discount`,
            DROP `percentage_global_discount`,
            DROP `minimum_order_amount`,
            DROP `minimum_order_amount_surcharge`;
        ');
    }
}
