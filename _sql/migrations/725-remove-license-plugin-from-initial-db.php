<?php

class Migrations_Migration725 extends Shopware\Framework\Migration\AbstractMigration
{
    /**
     * @param string $modus
     * @return void
     */
    public function up($modus)
    {
        if ($modus !== self::MODUS_INSTALL) {
            return;
        }
        $this->addSql('DELETE FROM `s_core_plugins` WHERE name = "License"');
    }
}
