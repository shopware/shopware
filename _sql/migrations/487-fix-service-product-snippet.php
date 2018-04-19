<?php
class Migrations_Migration487 Extends Shopware\Framework\Migration\AbstractMigration
{
    public function up($modus)
    {
        $sql = <<<'EOD'
            UPDATE `s_core_config_elements`
            SET `label`= 'Artikel-Freitextfeld für Dienstleistungsartikel'
            WHERE `name` = 'serviceAttrField'
            AND `label`= 'Artikel-Freitextfeld für Dienstleistungensartikel'
EOD;
        $this->addSql($sql);
    }
}
