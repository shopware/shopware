<?php
class Migrations_Migration310 Extends Shopware\Framework\Migration\AbstractMigration
{
    public function up($modus)
    {
        $sql = <<<'EOD'
SET @plugin_id = (SELECT id FROM s_core_plugins WHERE name='SwagUpdate');
SET @form_id   = (SELECT id FROM s_core_config_forms WHERE plugin_id = @plugin_id);
SET @locale_id = (SELECT id FROM s_core_locales WHERE locale LIKE "de_DE");

SET @element_id_channel = (SELECT id FROM s_core_config_elements WHERE form_id = @form_id and name LIKE "update-channel" LIMIT 1);
SET @element_id_code = (SELECT id FROM s_core_config_elements WHERE form_id = @form_id and name LIKE "update-code" LIMIT 1);
SET @element_id_feedback = (SELECT id FROM s_core_config_elements WHERE form_id = @form_id and name LIKE "update-send-feedback" LIMIT 1);

INSERT IGNORE INTO s_core_config_element_translations (element_id, locale_id, label) VALUES
(@element_id_feedback, @locale_id, 'Feedback senden'),
(@element_id_code,     @locale_id, 'Aktionscode'),
(@element_id_channel,  @locale_id, 'Update Kanal');
EOD;
        $this->addSql($sql);
    }
}
