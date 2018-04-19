<?php

class Migrations_Migration630 extends Shopware\Framework\Migration\AbstractMigration
{
    public function up($modus)
    {
        $sql = <<<'EOD'
SET @pluginId = (SELECT id FROM s_core_plugins WHERE name = 'Google' and namespace = 'Frontend' and `source` = 'Default' LIMIT 1);
SET @formId = (SELECT id FROM s_core_config_forms WHERE plugin_id = @pluginId);

DELETE FROM s_core_config_elements WHERE form_id = @formId;
EOD;
        $this->addSql($sql);

        $sql = <<<'EOD'
DELETE FROM s_core_config_forms WHERE id = @formId;
EOD;
        $this->addSql($sql);

        $sql = <<<'EOD'
DELETE FROM s_core_plugins WHERE name = 'Google' and namespace = 'Frontend' and source = 'Default'
EOD;
        $this->addSql($sql);
    }
}
