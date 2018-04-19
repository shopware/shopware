<?php
class Migrations_Migration137 Extends Shopware\Framework\Migration\AbstractMigration
{
    public function up($modus)
    {
        $sql = <<<'EOD'
SET @formId = (SELECT id FROM s_core_config_forms WHERE name = 'LastArticles' LIMIT 1);
SET @elementId = (SELECT id FROM s_core_config_elements WHERE name = 'time' AND form_id = @formId LIMIT 1);

DELETE FROM s_core_config_element_translations WHERE element_id = @elementId;
DELETE FROM s_core_config_values WHERE element_id = @elementId;
DELETE FROM s_core_config_elements WHERE id = @elementId;
EOD;
        $this->addSql($sql);
    }
}
