<?php
class Migrations_Migration139 Extends Shopware\Framework\Migration\AbstractMigration
{
    public function up($modus)
    {
        $sql = <<<'EOD'
        SET @formId = (SELECT id FROM `s_core_config_forms` WHERE `name` = 'Plugin' AND `label` = 'Plugins' LIMIT 1);
        DELETE FROM `s_core_config_form_translations` WHERE `form_id` = @formId;
        DELETE FROM `s_core_config_forms` WHERE `id` = @formId;
EOD;
        $this->addSql($sql);
    }
}
