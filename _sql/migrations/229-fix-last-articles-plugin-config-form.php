<?php
class Migrations_Migration229 Extends Shopware\Framework\Migration\AbstractMigration
{
    public function up($modus)
    {
        $sql = <<<'EOD'
SET @plugin_id = (SELECT id FROM `s_core_plugins` WHERE `name`='LastArticles' LIMIT 1);
SET @parent_form = (SELECT id FROM `s_core_config_forms` WHERE `name`='LastArticles' AND plugin_id = @plugin_id LIMIT 1);
SET @thumb_element = (SELECT id FROM s_core_config_elements WHERE form_id = @parent_form and name='thumb' LIMIT 1);
SET @localeID = (SELECT id FROM s_core_locales WHERE locale='en_GB' LIMIT 1);

UPDATE s_core_config_element_translations SET label = 'Thumbnail size', description = 'Index of the thumbnail size of the associated album to use. Starts at 0'
WHERE element_id = @thumb_element AND locale_id = @localeID AND label = 'Size of display' AND description IS NULL;

INSERT IGNORE INTO `s_core_config_elements` (`form_id`, `name`, `value`, `label`, `description`, `type`, `required`, `position`, `scope`, `filters`, `validators`, `options`) VALUES
(@parent_form, 'time', 'i:15;', 'Speicherfrist in Tagen', NULL, 'number', 0, 0, 0, NULL, NULL, 'a:0:{}');

SET @time_element = (SELECT id FROM s_core_config_elements WHERE form_id = @parent_form and name='time' LIMIT 1);

INSERT IGNORE INTO `s_core_config_element_translations` (`element_id`, `locale_id`, `label`, `description`) VALUES
(@time_element, @localeID, 'Storage period in days', NULL);
EOD;
        $this->addSql($sql);
    }
}
