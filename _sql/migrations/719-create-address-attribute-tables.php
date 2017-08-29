<?php

class Migrations_Migration719 extends Shopware\Framework\Migration\AbstractMigration
{
    /**
     * @param string $modus
     * @return void
     */
    public function up($modus)
    {
        $this->createAddressAttributeTable();

        $tables = [
            's_order_billingaddress_attributes',
            's_order_shippingaddress_attributes',
            's_user_shippingaddress_attributes',
            's_user_billingaddress_attributes'
        ];

        foreach ($tables as $table) {
            $this->applyAttributeSchema($table);
        }
    }

    private function createAddressAttributeTable()
    {
        $sql = <<<SQL
CREATE TABLE `s_user_addresses_attributes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `address_id` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `address_id` (`address_id`),
  CONSTRAINT `s_user_addresses_attributes_ibfk_1` FOREIGN KEY (`address_id`) REFERENCES `s_user_addresses` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL;
        $this->connection->exec($sql);
    }

    /**
     * @param string $table
     * @throws Exception
     */
    private function applyAttributeSchema($table)
    {
        require_once __DIR__ . '/common/MigrationHelper.php';
        $helper = new MigrationHelper($this->connection);

        $attributes = $helper->getList($table);

        foreach ($attributes as $attribute) {
            $helper->update('s_user_addresses_attributes', $attribute['name'], $attribute['type']);
        }
    }
}
