<?php
class Migrations_Migration358 Extends Shopware\Framework\Migration\AbstractMigration
{
    public function up($modus)
    {
        $sql = <<<'EOD'
            SET @parent = (SELECT id FROM s_core_config_forms WHERE name = 'Frontend33' LIMIT 1);

            INSERT IGNORE INTO `s_core_config_elements` (`form_id`, `name`, `value`, `label`, `description`, `type`, `required`, `position`, `scope`, `filters`, `validators`, `options`) VALUES
            (@parent, 'showphonenumberfield', 'b:1;', 'Telefon anzeigen', NULL, 'checkbox', 0, 0, 1, NULL, NULL, 'a:0:{}');

            SET @newElementId = (SELECT id FROM `s_core_config_elements` WHERE `name` = 'showphonenumberfield' LIMIT 1);
            INSERT IGNORE INTO `s_core_config_element_translations` (`element_id`, `locale_id`, `label`)
            VALUES (@newElementId, '2', 'Show phone number field');


            INSERT IGNORE INTO `s_core_config_elements` (`form_id`, `name`, `value`, `label`, `description`, `type`, `required`, `position`, `scope`, `filters`, `validators`, `options`) VALUES
            (@parent, 'doublepasswordvalidation', 'b:1;', 'Passwort muss zweimal eingegeben werden', 'Passwort muss zweimal angegeben werden, um Tippfehler zu vermeiden.', 'checkbox', 0, 0, 1, NULL, NULL, 'a:0:{}');

            SET @elementId = (SELECT id FROM `s_core_config_elements` WHERE `name` = 'doublepasswordvalidation' LIMIT 1);
            INSERT IGNORE INTO `s_core_config_element_translations` (`element_id`, `locale_id`, `label`, `description`)
            VALUES (@elementId, '2', 'Password must be entered twice.', 'Password must be entered twice in order to avoid typing errors');


            INSERT IGNORE INTO `s_core_config_elements` (`form_id`, `name`, `value`, `label`, `description`, `type`, `required`, `position`, `scope`, `filters`, `validators`, `options`) VALUES
            (@parent, 'showbirthdayfield', 'b:1;', 'Geburtstag anzeigen', NULL, 'checkbox', 0, 0, 1, NULL, NULL, 'a:0:{}');

            SET @newElementId = (SELECT id FROM `s_core_config_elements` WHERE `name` = 'showbirthdayfield' LIMIT 1);
            INSERT IGNORE INTO `s_core_config_element_translations` (`element_id`, `locale_id`, `label`)
            VALUES (@newElementId, '2', 'Show Birthday field');


            INSERT IGNORE INTO `s_core_config_elements` (`form_id`, `name`, `value`, `label`, `description`, `type`, `required`, `position`, `scope`, `filters`, `validators`, `options`) VALUES
            (@parent, 'requirebirthdayfield', 'b:0;', 'Geburtstag als Pflichtfeld behandeln', NULL, 'checkbox', 0, 0, 1, NULL, NULL, 'a:0:{}');

            SET @newElementId = (SELECT id FROM `s_core_config_elements` WHERE `name` = 'requirebirthdayfield' LIMIT 1);
            INSERT IGNORE INTO `s_core_config_element_translations` (`element_id`, `locale_id`, `label`)
            VALUES (@newElementId, '2', 'Birthday is required');
EOD;
        $this->addSql($sql);
    }
}
