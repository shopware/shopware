<?php

class Migrations_Migration775 extends Shopware\Framework\Migration\AbstractMigration
{
    public function up($modus)
    {
        $this->createUniqueIndex();

        $sql = <<<SQL
INSERT INTO `s_core_config_elements` (`id`, `form_id`, `name`, `value`, `label`, `description`, `type`, `required`, `position`, `scope`)
VALUES (NULL, '0', 'assetTimestamp', 'i:0;', '', 'Cache invalidation timestamp for assets', '', '0', '0', '1')
SQL;
        $this->addSql($sql);
    }

    private function createUniqueIndex()
    {
        $this->addSql('CREATE TABLE `s_core_config_values_unique` LIKE `s_core_config_values`');
        $this->addSql('ALTER TABLE `s_core_config_values_unique` ADD UNIQUE `element_id_shop_id` (`element_id`, `shop_id`)');
        $this->addSql('INSERT IGNORE INTO `s_core_config_values_unique` SELECT * FROM `s_core_config_values`');
        $this->addSql('DROP TABLE `s_core_config_values`');
        $this->addSql('RENAME TABLE `s_core_config_values_unique` TO `s_core_config_values`');
    }
}
