<?php
class Migrations_Migration307 Extends Shopware\Framework\Migration\AbstractMigration
{
    public function up($modus)
    {
        $sql = <<<'EOD'
UPDATE `s_core_menu` SET `class` = 'ico question_frame shopware-help-menu' WHERE `class` = 'ico question_frame';

INSERT IGNORE INTO `s_core_plugins` (`namespace`, `name`, `label`, `source`, `description`, `description_long`, `active`, `added`, `installation_date`, `update_date`, `refresh_date`, `author`, `copyright`, `license`, `version`, `support`, `changes`, `link`, `store_version`, `store_date`, `capability_update`, `capability_install`, `capability_enable`, `capability_dummy`, `update_source`, `update_version`) VALUES ('Backend', 'SwagUpdate', 'Shopware Auto Update', 'Default', NULL, NULL, '1', '2014-05-06 09:03:01', '2014-05-06 09:03:06', '2014-05-06 09:03:06', '2014-05-06 09:03:09', 'shopware AG', 'Copyright © 2012, shopware AG', NULL, '1.0.0', NULL, NULL, NULL, NULL, NULL, '1', '1', '1', '0', NULL, NULL);

SET @plugin_id = (SELECT id FROM s_core_plugins WHERE name='SwagUpdate');


INSERT IGNORE INTO `s_core_menu` (`parent`, `hyperlink`, `name`, `onclick`, `style`, `class`, `position`, `active`, `pluginID`, `resourceID`, `controller`, `shortcut`, `action`) VALUES ('40', '', 'SwagUpdate', NULL, NULL, 'sprite-arrow-continue-090', '0', '1', @plugin_id, NULL, 'SwagUpdate', NULL, 'Index');

INSERT IGNORE INTO `s_core_subscribes` (`subscribe`, `type`, `listener`, `pluginID`, `position`) VALUES ('Enlight_Controller_Action_PostDispatch_Backend_Index', '0', 'Shopware_Plugins_Backend_SwagUpdate_Bootstrap::onBackendIndexPostDispatch', @plugin_id, '0');
INSERT IGNORE INTO `s_core_subscribes` (`subscribe`, `type`, `listener`, `pluginID`, `position`) VALUES ('Enlight_Controller_Dispatcher_ControllerPath_Backend_SwagUpdate', '0', 'Shopware_Plugins_Backend_SwagUpdate_Bootstrap::onGetSwagUpdateControllerPath', @plugin_id, '0');
INSERT IGNORE INTO `s_core_subscribes` (`subscribe`, `type`, `listener`, `pluginID`, `position`) VALUES ('Enlight_Bootstrap_InitResource_SwagUpdateUpdateCheck', '0', 'Shopware_Plugins_Backend_SwagUpdate_Bootstrap::onInitUpdateCheck', @plugin_id, '0');

SET @parent_form_id = (SELECT id FROM  `s_core_config_forms` WHERE `name` LIKE "Core");


INSERT IGNORE INTO `s_core_config_forms` (`parent_id`, `name`, `label`, `description`, `position`, `scope`, `plugin_id`) VALUES (@parent_form_id, 'SwagUpdate', 'Shopware Auto Update', NULL, '0', '0', @plugin_id);

SET @form_id = (SELECT id FROM  `s_core_config_forms` WHERE plugin_id = @plugin_id);

INSERT IGNORE INTO `s_core_config_elements` (`form_id`, `name`, `value`, `label`, `description`, `type`, `required`, `position`, `scope`, `filters`, `validators`, `options`) VALUES (@form_id, 'update-api-endpoint', 's:34:\"http://update-api.shopware.com/v1/\";', 'API Endpoint', NULL, 'text', '1', '0', '0', NULL, NULL, 'a:1:{s:6:\"hidden\";b:1;}');
INSERT IGNORE INTO `s_core_config_elements` (`form_id`, `name`, `value`, `label`, `description`, `type`, `required`, `position`, `scope`, `filters`, `validators`, `options`) VALUES (@form_id, 'update-channel', 's:6:\"stable\";', 'Channel', NULL, 'select', '0', '0', '0', NULL, NULL, 'a:1:{s:5:\"store\";a:4:{i:0;a:2:{i:0;s:6:\"stable\";i:1;s:6:\"stable\";}i:1;a:2:{i:0;s:4:\"beta\";i:1;s:4:\"beta\";}i:2;a:2:{i:0;s:2:\"rc\";i:1;s:2:\"rc\";}i:3;a:2:{i:0;s:3:\"dev\";i:1;s:3:\"dev\";}}}');
INSERT IGNORE INTO `s_core_config_elements` (`form_id`, `name`, `value`, `label`, `description`, `type`, `required`, `position`, `scope`, `filters`, `validators`, `options`) VALUES (@form_id, 'update-code', 's:0:\"\";', 'Code', NULL, 'text', '0', '0', '0', NULL, NULL, 'a:0:{}');
INSERT IGNORE INTO `s_core_config_elements` (`form_id`, `name`, `value`, `label`, `description`, `type`, `required`, `position`, `scope`, `filters`, `validators`, `options`) VALUES (@form_id, 'update-fake-version', 'N;', 'Fake Version', NULL, 'text', '0', '0', '0', NULL, NULL, 'a:1:{s:6:\"hidden\";b:1;}');
INSERT IGNORE INTO `s_core_config_elements` (`form_id`, `name`, `value`, `label`, `description`, `type`, `required`, `position`, `scope`, `filters`, `validators`, `options`) VALUES (@form_id, 'update-feedback-api-endpoint', 's:43:\"http://feedback.update-api.shopware.com/v1/\";', 'Feedback API Endpoint', NULL, 'text', '1', '0', '0', NULL, NULL, 'a:1:{s:6:\"hidden\";b:1;}');
INSERT IGNORE INTO `s_core_config_elements` (`form_id`, `name`, `value`, `label`, `description`, `type`, `required`, `position`, `scope`, `filters`, `validators`, `options`) VALUES (@form_id, 'update-send-feedback', 'b:1;', 'Send feedback', NULL, 'boolean', '0', '0', '0', NULL, NULL, 'a:0:{}');
INSERT IGNORE INTO `s_core_config_elements` (`form_id`, `name`, `value`, `label`, `description`, `type`, `required`, `position`, `scope`, `filters`, `validators`, `options`) VALUES (@form_id, 'update-unique-id', 's:0:\"\";', 'Unique identifier', NULL, 'text', '0', '0', '0', NULL, NULL, 'a:1:{s:6:\"hidden\";b:1;}');
INSERT IGNORE INTO `s_core_config_elements` (`form_id`, `name`, `value`, `label`, `description`, `type`, `required`, `position`, `scope`, `filters`, `validators`, `options`) VALUES (@form_id, 'update-verify-signature', 'b:1;', 'Verify Signature', NULL, 'boolean', '0', '0', '0', NULL, NULL, 'a:1:{s:6:\"hidden\";b:1;}');
EOD;
        $this->addSql($sql);
    }
}
