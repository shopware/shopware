<?php
class Migrations_Migration222 Extends Shopware\Framework\Migration\AbstractMigration
{
    public function up($modus)
    {
        $sql = <<<EOD
SET @plugin_id = (SELECT id FROM s_core_plugins WHERE name='ErrorHandler');

SET @parent_form_id = (SELECT id FROM  `s_core_config_forms` WHERE `name` LIKE "Core");
INSERT IGNORE INTO `s_core_config_forms` (`parent_id`, `name`, `label`, `description`, `position`, `scope`, `plugin_id`) VALUES (@parent_form_id, 'Log', 'Log', NULL, '0', '0', @plugin_id);

SET @form_id = (SELECT id FROM  `s_core_config_forms` WHERE plugin_id = @plugin_id);
INSERT IGNORE INTO `s_core_config_elements` (`form_id`, `name`, `value`, `label`, `description`, `type`, `required`, `position`, `scope`, `filters`, `validators`, `options`) VALUES (@form_id, 'logMail', 'i:0;', 'Fehler an Shopbetreiber senden', NULL, 'checkbox', '0', '0', '0', NULL, NULL, 'a:0:{}');
EOD;

        $this->addSql($sql);
    }
}

