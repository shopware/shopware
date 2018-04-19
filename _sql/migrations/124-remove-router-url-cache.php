<?php
class Migrations_Migration124 Extends Shopware\Framework\Migration\AbstractMigration
{
    public function up($modus)
    {
        $sql = <<<'EOD'
SET @elementId = (SELECT id FROM s_core_config_elements WHERE name LIKE 'routerurlcache');

DELETE FROM s_core_config_element_translations WHERE element_id = @elementId;
DELETE FROM s_core_config_values WHERE element_id = @elementId;
DELETE FROM s_core_config_elements WHERE id = @elementId;
EOD;

        $this->addSql($sql);
    }
}
