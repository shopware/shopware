<?php

class Migrations_Migration493 Extends Shopware\Framework\Migration\AbstractMigration
{
    public function up($modus)
    {
        if ($modus === self::MODUS_INSTALL) {
            $this->removeTranslations();
        }
    }

    /**
     * Translations moved to en.sql in the installer
     */
    private function removeTranslations()
    {
        $this->addSql("TRUNCATE s_core_translations;");
    }
}
