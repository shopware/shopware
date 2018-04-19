<?php
class Migrations_Migration144 Extends Shopware\Framework\Migration\AbstractMigration
{
    public function up($modus)
    {
        $sql = <<<'EOD'
        SET @parent = (SELECT id FROM s_core_config_forms WHERE name = 'Frontend33' LIMIT 1);

        INSERT IGNORE INTO `s_core_config_elements` (`id`, `form_id`, `name`, `value`, `label`, `description`, `type`, `required`, `position`, `scope`, `filters`, `validators`, `options`) VALUES
        (NULL, @parent, 'requirePhoneField', 'b:1;', 'Telefon als Pflichtfeld behandeln', 'Beachten Sie, dass Sie die Sternchenangabe über den Textbaustein RegisterLabelPhone konfigurieren müssen', 'checkbox', 0, 0, 1, NULL, NULL, 'a:0:{}');

        SET @elementId = (SELECT id FROM `s_core_config_elements` WHERE `name` = 'requirePhoneField' LIMIT 1);
        INSERT IGNORE INTO `s_core_config_element_translations` (`element_id`, `locale_id`, `label`, `description`)
        VALUES (@elementId, '2', 'Treat phone field as required', 'Note that you must configure the asterisk indication in the snippet RegisterLabelPhone' );
EOD;
        $this->addSql($sql);
    }
}
