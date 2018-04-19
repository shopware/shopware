<?php
class Migrations_Migration126 Extends Shopware\Framework\Migration\AbstractMigration
{
    public function up($modus)
    {
        $sql = <<<'EOD'
DELETE FROM `s_core_menu` WHERE `name` = 'Artikel + Kategorien';
UPDATE `s_core_menu` SET `name` = 'Shopcache leeren' WHERE `name` = 'Konfiguration + Template';
EOD;

        $this->addSql($sql);
    }
}
