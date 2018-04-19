<?php
class Migrations_Migration453 Extends Shopware\Framework\Migration\AbstractMigration
{
    public function up($modus)
    {
        $sql = "
            ALTER TABLE `s_core_auth_roles` 
               CHANGE `name` `name` VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
               CHANGE `description` `description` TEXT CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
               CHANGE `source` `source` VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL;
        ";
        $this->addSql($sql);
        
        $sql = "
            ALTER TABLE `s_statistics_search` 
              CHANGE `searchterm` `searchterm` VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL;
        ";
        $this->addSql($sql);
    }
}
