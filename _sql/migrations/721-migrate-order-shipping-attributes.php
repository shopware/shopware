<?php

class Migrations_Migration721 extends Shopware\Framework\Migration\AbstractMigration
{
    /**
     * @param string $modus
     * @return void
     */
    public function up($modus)
    {
        require_once __DIR__ . '/common/MigrationHelper.php';
        $helper = new MigrationHelper($this->connection);

        $helper->migrateAttributes('s_order_shippingaddress_attributes', 'shippingID');
    }
}
