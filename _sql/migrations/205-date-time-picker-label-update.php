<?php
class Migrations_Migration205 Extends Shopware\Framework\Migration\AbstractMigration
{
    public function up($modus)
    {
        $sql = <<<'EOD'
            UPDATE s_core_config_element_translations SET label = "Last update" WHERE label = "Last update (dd.mm.yyyy)" AND locale_id = 2 AND element_id IN (SELECT id FROM s_core_config_elements WHERE name IN ("routerlastupdate", "fuzzysearchlastupdate"));
EOD;
        $this->addSql($sql);
    }
}
