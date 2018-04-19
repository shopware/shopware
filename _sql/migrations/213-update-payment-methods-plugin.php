<?php
class Migrations_Migration213 Extends Shopware\Framework\Migration\AbstractMigration
{
    public function up($modus)
    {
        $sql = <<<'EOD'
        UPDATE `s_core_plugins`
        SET label = 'Payment Methods',
        description = 'Shopware Payment Methods handling. This plugin is required to handle payment methods, and should not be deactivated',
        capability_enable = 1,
        capability_update = 1
        WHERE name = 'PaymentMethods' and version = '1.0.0' and author = 'shopware AG'
EOD;

        $this->addSql($sql);
    }
}
