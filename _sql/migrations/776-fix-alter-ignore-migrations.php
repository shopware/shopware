<?php

class Migrations_Migration776 extends Shopware\Framework\Migration\AbstractMigration
{
    public function up($modus)
    {
        $rows = $this->getConnection()->query("show index from s_core_payment_data WHERE Non_unique = 0 AND Column_name IN ('payment_mean_id','user_id')")->rowCount();
        if ($rows === 2) {
            return;
        }

        $this->addSql('CREATE TABLE `s_core_payment_data_unique` LIKE `s_core_payment_data`');
        $this->addSql('ALTER TABLE `s_core_payment_data_unique` ADD UNIQUE (`payment_mean_id`, `user_id`)');
        $this->addSql('INSERT IGNORE INTO `s_core_payment_data_unique` SELECT * FROM `s_core_payment_data`');
        $this->addSql('DROP TABLE `s_core_payment_data`');
        $this->addSql('RENAME TABLE `s_core_payment_data_unique` TO `s_core_payment_data`');
    }
}
