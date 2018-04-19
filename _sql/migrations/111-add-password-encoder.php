<?php
class Migrations_Migration111 Extends Shopware\Framework\Migration\AbstractMigration
{
    public function up($modus)
    {
        $sql = <<<'EOD'
INSERT IGNORE INTO `s_core_plugins` (`id`, `namespace`, `name`, `label`, `source`, `description`, `description_long`, `active`, `added`, `installation_date`, `update_date`, `refresh_date`, `author`, `copyright`, `license`, `version`, `support`, `changes`, `link`, `store_version`, `store_date`, `capability_update`, `capability_install`, `capability_enable`, `update_source`, `update_version`) VALUES
(NULL, 'Core', 'PasswordEncoder', 'PasswordEncoder', 'Default', NULL, NULL, 1, '2013-04-16 12:13:54', '2013-04-16 14:07:23', '2013-04-16 14:07:23', '2013-04-16 14:07:23', 'shopware AG', 'Copyright © 2013, shopware AG', NULL, '1.0.0', NULL, NULL, NULL, NULL, NULL, 1, 0, 0, NULL, NULL);

SET @plugin_id = (SELECT id FROM s_core_plugins WHERE name='PasswordEncoder');


INSERT IGNORE INTO `s_core_subscribes` (`subscribe`, `listener`, `pluginID`) VALUES
('Enlight_Bootstrap_InitResource_PasswordEncoder', 'Shopware_Plugins_Core_PasswordEncoder_Bootstrap::onInitResourcePasswordEncoder', @plugin_id),
('Shopware_Components_Password_Manager_AddEncoder', 'Shopware_Plugins_Core_PasswordEncoder_Bootstrap::onAddEncoder', @plugin_id);


SET @help_parent = (SELECT id FROM s_core_config_forms WHERE name='Core');

INSERT IGNORE INTO `s_core_config_forms` (`id`, `parent_id`, `name`, `label`, `description`, `position`, `scope`, `plugin_id`) VALUES
(NULL, @help_parent , 'Passwörter', 'Passwörter', NULL, 0, 0, @plugin_id);

SET @parent = (SELECT id FROM s_core_config_forms WHERE name = 'Passwörter' AND parent_id=@help_parent);

INSERT IGNORE INTO `s_core_config_elements`
(`form_id`, `name`, `value`, `label`, `description`, `type`, `required`, `position`, `scope`, `filters`, `validators`, `options`)
VALUES
(@parent, 'defaultPasswordEncoder', 's:4:"Auto";', 'Passwort-Algorithmus', 'Mit welchem Algorithmus sollen die Passwörter verschlüsselt werden?', 'combo', 1, 0, 0, NULL, NULL, 'a:5:{s:8:"editable";b:0;s:10:"valueField";s:2:"id";s:12:"displayField";s:2:"id";s:13:"triggerAction";s:3:"all";s:5:"store";s:20:"base.PasswordEncoder";}'),
(@parent, 'liveMigration', 'i:1;', 'Live Migration', 'Sollen vorhandene Benutzer-Passwörter mit anderen Passwort-Algorithmen beim nächsten Einloggen erneut gehasht werden? Das geschieht voll automatisch im Hintergrund, so dass die Passwörter sukzessiv auf einen neuen Algorithmus umgestellt werden können.', 'checkbox', 1, 0, 0, NULL, NULL, NULL),
(@parent, 'bcryptCost', 'i:10;', 'Bcrypt-Rechenaufwand', 'Je höher der Rechenaufwand, desto aufwändiger ist es für einen möglichen Angreifer, ein Klartext-Passwort für das verschlüsselte Passwort zu berechnen.', 'number', 1, 0, 0, NULL, NULL, 'a:2:{s:8:"minValue";s:1:"4";s:8:"maxValue";s:2:"31";}'),
(@parent, 'sha256iterations', 'i:100000;', 'Sha256-Iterationen', 'Je höher der Rechenaufwand, desto aufwändiger ist es für einen möglichen Angreifer, ein Klartext-Passwort für das verschlüsselte Passwort zu berechnen.', 'number', 1, 0, 0, NULL, NULL, 'a:2:{s:8:"minValue";s:1:"1";s:8:"maxValue";s:7:"1000000";}');

ALTER TABLE  `s_core_auth` ADD  `encoder` VARCHAR( 255 ) NOT NULL DEFAULT  'LegacyBackendMd5' AFTER  `password`;
ALTER TABLE  `s_core_auth` CHANGE  `password`  `password` VARCHAR( 255 ) NOT NULL;

ALTER TABLE  `s_user` ADD  `encoder` VARCHAR( 255 ) NOT NULL DEFAULT  'md5' AFTER  `password`;
ALTER TABLE  `s_user` CHANGE  `password`  `password` VARCHAR( 255 ) NOT NULL;
EOD;

        $this->addSql($sql);
    }
}
