<?php

class Migrations_Migration724 extends Shopware\Framework\Migration\AbstractMigration
{
    /**
     * @param string $modus
     * @return void
     */
    public function up($modus)
    {
        $this->addSql("ALTER TABLE `s_user_addresses` DROP `original_type`");
        $this->addSql("ALTER TABLE `s_user_addresses` DROP `original_id`");
        if ($modus == self::MODUS_INSTALL) {
            return;
        }

        $this->addSql("DROP TABLE IF EXISTS `s_user_addresses_migration`");
        $this->addSql("ALTER TABLE `s_user_addresses` DROP `migration_id`");
    }
}
