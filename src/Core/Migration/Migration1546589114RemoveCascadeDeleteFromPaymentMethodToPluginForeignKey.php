<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1546589114RemoveCascadeDeleteFromPaymentMethodToPluginForeignKey extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1546589114;
    }

    public function update(Connection $connection): void
    {
        $query = <<<SQL
ALTER TABLE payment_method DROP FOREIGN KEY `fk.payment_method.plugin_id`;
SQL;

        $connection->executeQuery($query);

        $query = <<<SQL
ALTER TABLE payment_method
	ADD CONSTRAINT `fk.payment_method.plugin_id`
		FOREIGN KEY (plugin_id) REFERENCES plugin (id)
			ON UPDATE CASCADE ON DELETE SET NULL;
SQL;

        $connection->executeQuery($query);
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
