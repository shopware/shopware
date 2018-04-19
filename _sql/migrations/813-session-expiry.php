<?php

class Migrations_Migration813 extends Shopware\Framework\Migration\AbstractMigration
{
    public function up($modus)
    {
        $this->migrateFrontendSession();
        $this->migrateBackendSession();
    }

    private function migrateFrontendSession()
    {
        $this->addSql('ALTER TABLE `s_core_sessions` CHANGE `expiry` `expiry` INTEGER UNSIGNED NOT NULL;');
        $this->addSql('CREATE INDEX idx_sess_expiry ON `s_core_sessions` (expiry);');
        $this->addSql('UPDATE `s_core_sessions` SET `expiry` = `expiry` + `modified`;');
    }

    private function migrateBackendSession()
    {
        $this->addSql('ALTER TABLE `s_core_sessions_backend` CHANGE `expiry` `expiry` INTEGER UNSIGNED NOT NULL;');
        $this->addSql('CREATE INDEX idx_sess_expiry ON `s_core_sessions_backend` (expiry);');
        $this->addSql('UPDATE `s_core_sessions_backend` SET `expiry` = `expiry` + `modified`;');
    }
}
