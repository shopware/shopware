<?php

class Migrations_Migration772 extends Shopware\Framework\Migration\AbstractMigration
{
    public function up($modus)
    {
        $this->addSql("ALTER TABLE `s_attribute_configuration` CHANGE `label` `label` VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL;");
        $this->addSql("ALTER TABLE `s_attribute_configuration` DROP `plugin_id`;");
    }
}
