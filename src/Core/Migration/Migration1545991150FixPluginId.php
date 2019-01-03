<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Struct\Uuid;

class Migration1545991150FixPluginId extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1545991150;
    }

    public function update(Connection $connection): void
    {
        $this->removeForeignKeyFromPaymentMethod($connection);
        $this->changePluginIdColumn($connection);
        $this->changePaymentMethodPluginIdColumn($connection);
    }

    public function updateDestructive(Connection $connection): void
    {
    }

    private function removeForeignKeyFromPaymentMethod(Connection $connection): void
    {
        $connection->executeQuery(
            'ALTER TABLE `payment_method`
            DROP FOREIGN KEY `fk.payment_method.plugin_id`;'
        );
    }

    private function changePluginIdColumn(Connection $connection): void
    {
        $plugins = $connection->executeQuery('SELECT * FROM `plugin`')->fetchAll();

        $connection->executeQuery('TRUNCATE `plugin`');

        $connection->executeQuery(
            'ALTER TABLE `plugin`
            MODIFY COLUMN `id` BINARY(16) NOT NULL;'
        );

        foreach ($plugins as $plugin) {
            $plugin['id'] = Uuid::uuid4()->getBytes();
            $connection->insert('plugin', $plugin);
        }
    }

    private function changePaymentMethodPluginIdColumn(Connection $connection): void
    {
        $connection->executeUpdate(
            'UPDATE `payment_method`
            SET `plugin_id` = NULL;'
        );

        $connection->executeQuery(
            'ALTER TABLE `payment_method`
            MODIFY COLUMN `plugin_id` BINARY(16) NULL,
            ADD CONSTRAINT `fk.payment_method.plugin_id` FOREIGN KEY (`plugin_id`) REFERENCES `plugin` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;'
        );
    }
}
