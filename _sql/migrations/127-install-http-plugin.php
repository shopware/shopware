<?php
class Migrations_Migration127 Extends Shopware\Framework\Migration\AbstractMigration
{
    public function up($modus)
    {
        $sql = <<<'EOD'
INSERT IGNORE INTO `s_core_plugins` (`namespace`, `name`, `label`, `source`, `description`, `description_long`, `active`, `added`, `installation_date`, `update_date`, `refresh_date`, `author`, `copyright`, `license`, `version`, `support`, `changes`, `link`, `store_version`, `store_date`, `capability_update`, `capability_install`, `capability_enable`, `capability_dummy`, `update_source`, `update_version`) VALUES ('Core', 'HttpCache', 'Frontendcache (HttpCache)', 'Default', NULL, NULL, '0', '2013-05-27 15:57:59', '2013-05-27 15:58:09', '2013-05-27 15:58:09', '2013-05-27 15:58:10', 'shopware AG', 'Copyright © 2012, shopware AG', NULL, '1.1.0', NULL, NULL, NULL, NULL, NULL, '1', '0', '1', '0', NULL, NULL);

SET @plugin_id = (SELECT id FROM s_core_plugins WHERE name='HttpCache');

INSERT IGNORE INTO `s_core_subscribes` (`subscribe`, `type`, `listener`, `pluginID`, `position`) VALUES ('Enlight_Controller_Action_PreDispatch', '0', 'Shopware_Plugins_Core_HttpCache_Bootstrap::onPreDispatch', @plugin_id, '0');
INSERT IGNORE INTO `s_core_subscribes` (`subscribe`, `type`, `listener`, `pluginID`, `position`) VALUES ('Shopware\\Models\\Article\\Article::postPersist', '0', 'Shopware_Plugins_Core_HttpCache_Bootstrap::onPostPersist', @plugin_id, '0');
INSERT IGNORE INTO `s_core_subscribes` (`subscribe`, `type`, `listener`, `pluginID`, `position`) VALUES ('Shopware\\Models\\Category\\Category::postPersist', '0', 'Shopware_Plugins_Core_HttpCache_Bootstrap::onPostPersist', @plugin_id, '0');
INSERT IGNORE INTO `s_core_subscribes` (`subscribe`, `type`, `listener`, `pluginID`, `position`) VALUES ('Shopware\\Models\\Banner\\Banner::postPersist', '0', 'Shopware_Plugins_Core_HttpCache_Bootstrap::onPostPersist', @plugin_id, '0');
INSERT IGNORE INTO `s_core_subscribes` (`subscribe`, `type`, `listener`, `pluginID`, `position`) VALUES ('Shopware\\Models\\Article\\Article::postUpdate', '0', 'Shopware_Plugins_Core_HttpCache_Bootstrap::onPostPersist', @plugin_id, '0');
INSERT IGNORE INTO `s_core_subscribes` (`subscribe`, `type`, `listener`, `pluginID`, `position`) VALUES ('Shopware\\Models\\Category\\Category::postUpdate', '0', 'Shopware_Plugins_Core_HttpCache_Bootstrap::onPostPersist', @plugin_id, '0');
INSERT IGNORE INTO `s_core_subscribes` (`subscribe`, `type`, `listener`, `pluginID`, `position`) VALUES ('Shopware\\Models\\Banner\\Banner::postUpdate', '0', 'Shopware_Plugins_Core_HttpCache_Bootstrap::onPostPersist', @plugin_id, '0');

SET @parent_form_id = (SELECT id FROM  `s_core_config_forms` WHERE `name` LIKE "Core");

INSERT IGNORE INTO `s_core_config_forms` (`parent_id`, `name`, `label`, `description`, `position`, `scope`, `plugin_id`) VALUES (@parent_form_id, 'HttpCache', 'Frontendcache (HttpCache)', NULL, '0', '0', @plugin_id);

SET @form_id = (SELECT id FROM  `s_core_config_forms` WHERE plugin_id = @plugin_id);

INSERT IGNORE INTO `s_core_config_elements` (`form_id`, `name`, `value`, `label`, `description`, `type`, `required`, `position`, `scope`, `filters`, `validators`, `options`) VALUES (@form_id, 'admin', 'b:0;', 'Admin-View', 'Cache bei Artikel-Vorschau und Schnellbestellung deaktivieren', 'boolean', '0', '0', '0', NULL, NULL, 'a:0:{}');
INSERT IGNORE INTO `s_core_config_elements` (`form_id`, `name`, `value`, `label`, `description`, `type`, `required`, `position`, `scope`, `filters`, `validators`, `options`) VALUES (@form_id, 'cacheControllers', 's:326:\"frontend/listing 3600\r\nfrontend/index 3600\r\nfrontend/detail 3600\r\nfrontend/campaign 14400\r\nwidgets/listing 14400\r\nfrontend/custom 14400\r\nfrontend/sitemap 14400\r\nfrontend/blog 14400\r\nwidgets/index 3600\r\nwidgets/checkout 3600\r\nwidgets/compare 3600\r\nwidgets/emotion 14400\r\nwidgets/recommendation 14400\r\nwidgets/lastArticles 3600\n\";', 'Cache-Controller / Zeiten', NULL, 'textarea', '0', '0', '0', NULL, NULL, 'a:0:{}');
INSERT IGNORE INTO `s_core_config_elements` (`form_id`, `name`, `value`, `label`, `description`, `type`, `required`, `position`, `scope`, `filters`, `validators`, `options`) VALUES (@form_id, 'noCacheControllers', 's:166:\"frontend/listing price\nfrontend/index price\nfrontend/detail price\nwidgets/lastArticles detail\nwidgets/checkout checkout\nwidgets/compare compare\nwidgets/emotion price\n\";', 'NoCache-Controller / Tags', NULL, 'textarea', '0', '0', '0', NULL, NULL, 'a:0:{}');
INSERT IGNORE INTO `s_core_config_elements` (`form_id`, `name`, `value`, `label`, `description`, `type`, `required`, `position`, `scope`, `filters`, `validators`, `options`) VALUES (@form_id, 'proxy', 'N;', 'Alternative Proxy-Url', 'Link zum Http-Proxy mit „http://“ am Anfang.', 'text', '0', '0', '0', NULL, NULL, 'a:0:{}');
INSERT IGNORE INTO `s_core_config_elements` (`form_id`, `name`, `value`, `label`, `description`, `type`, `required`, `position`, `scope`, `filters`, `validators`, `options`) VALUES (@form_id, 'proxyPrune', 'b:1;', 'Proxy-Prune aktivieren', 'Das automatische Leeren des Caches aktivieren.', 'boolean', '0', '0', '0', NULL, NULL, 'a:0:{}');
EOD;

        $this->addSql($sql);
    }
}
