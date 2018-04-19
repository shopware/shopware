<?php
class Migrations_Migration368 extends Shopware\Framework\Migration\AbstractMigration
{
    public function up($modus)
    {
        $sql = <<<'EOD'
        UPDATE s_cms_support_fields SET label = 'Straße ; Hausnummer' WHERE label = 'Straße / Hausnummer' AND typ = 'text2';
        UPDATE s_cms_support_fields SET label = 'PLZ ; Ort' WHERE label = 'PLZ / Ort' AND typ = 'text2';
        UPDATE s_cms_support_fields SET label = 'Street ; house number' WHERE label = 'Street / house number' AND typ = 'text2';
        UPDATE s_cms_support_fields SET label = 'Postal Code ; City' WHERE label = 'Postal Code / City' AND typ = 'text2';
EOD;
        $this->addSql($sql);
    }
}



