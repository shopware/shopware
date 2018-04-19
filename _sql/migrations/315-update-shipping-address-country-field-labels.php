<?php
class Migrations_Migration315 Extends Shopware\Framework\Migration\AbstractMigration
{
    public function up($modus)
    {
        $sql = <<<'EOD'
            SET @elementId = (SELECT id FROM s_core_config_elements WHERE name = 'countryshipping' LIMIT 1);
EOD;
        $this->addSql($sql);

        $sql = <<<'EOD'
            UPDATE s_core_config_elements
            SET label = 'Land / Bundesland bei Lieferadresse abfragen'
            WHERE label = 'Land bei Lieferadresse abfragen'
            AND id = @elementId;
EOD;
        $this->addSql($sql);

        $sql = <<<'EOD'
            UPDATE s_core_config_element_translations
            SET label = 'Display country and state fields in shipping address forms'
            WHERE label = 'Require country with shipping address'
            AND element_id = @elementId;
EOD;
        $this->addSql($sql);
    }
}
