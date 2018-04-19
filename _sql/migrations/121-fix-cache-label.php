<?php
class Migrations_Migration121 Extends Shopware\Framework\Migration\AbstractMigration
{
    public function up($modus)
    {
        $sql = <<<'EOD'
UPDATE `s_core_menu` SET name = 'Performance' WHERE name = 'Shopcache leeren';
UPDATE `s_core_config_elements` SET form_id = NULL WHERE name = 'cachesearch';
DELETE FROM `s_core_config_forms` WHERE name = 'QueryCache';

INSERT IGNORE INTO `s_core_config_elements`
  (`name`, `value`, `label`, `description`, `type`, `required`, `position`, `scope`, `filters`, `validators`, `options`)
VALUES
('traceSearch', 'i:1;', '', '', '', 1, 0, 0, NULL, NULL, '');



INSERT IGNORE INTO `s_core_plugins` (`id`, `namespace`, `name`, `label`, `source`, `description`, `description_long`, `active`, `added`, `installation_date`, `update_date`, `refresh_date`, `author`, `copyright`, `license`, `version`, `support`, `changes`, `link`, `store_version`, `store_date`, `capability_update`, `capability_install`, `capability_enable`, `update_source`, `update_version`) VALUES
(NULL, 'Core', 'RebuildIndex', 'Shopware Such- und SEO-Index', 'Default', NULL, NULL, 1, '2013-05-19 10:53:24', '2013-05-21 13:28:04', '2013-05-21 13:28:04', '2013-05-21 13:28:07', 'shopware AG', 'Copyright © 2012, shopware AG', NULL, '1.0.0', NULL, NULL, 'http://www.shopware.de/', NULL, NULL, 1, 1, 1, NULL, NULL);

SET @pluginId = (SELECT id FROM s_core_plugins WHERE name = 'RebuildIndex');

INSERT IGNORE INTO `s_core_subscribes` (`id`, `subscribe`, `type`, `listener`, `pluginID`, `position`) VALUES
(NULL, 'Enlight_Controller_Dispatcher_ControllerPath_Backend_Seo', 0, 'Shopware_Plugins_Core_RebuildIndex_Bootstrap::getSeoBackendController', @pluginId , 0),
(NULL, 'Enlight_Bootstrap_InitResource_SeoIndex', 0, 'Shopware_Plugins_Core_RebuildIndex_Bootstrap::initSeoIndexResource', @pluginId , 0),
(NULL, 'Enlight_Controller_Front_DispatchLoopShutdown', 0, 'Shopware_Plugins_Core_RebuildIndex_Bootstrap::onAfterSendResponse', @pluginId , 0),
(NULL, 'Shopware_CronJob_RefreshSeoIndex', 0, 'Shopware_Plugins_Core_RebuildIndex_Bootstrap::onRefreshSeoIndex', @pluginId , 0),
(NULL, 'Enlight_Controller_Dispatcher_ControllerPath_Backend_SearchIndex', 0, 'Shopware_Plugins_Core_RebuildIndex_Bootstrap::getSearchIndexBackendController', @pluginId, 0),
(NULL, 'Shopware_CronJob_RefreshSearchIndex', 0, 'Shopware_Plugins_Core_RebuildIndex_Bootstrap::refreshSearchIndex', @pluginId , 0);

INSERT IGNORE INTO `s_crontab` (`id`, `name`, `action`, `elementID`, `data`, `next`, `start`, `interval`, `active`, `end`, `inform_template`, `inform_mail`, `pluginID`) VALUES
(NULL, 'Refresh seo index', 'RefreshSeoIndex', NULL, '', '2013-05-21 13:28:04', NULL, 86400, 1, '2013-05-21 13:28:04', '', '', @pluginId),
(NULL, 'Refresh search index', 'RefreshSearchIndex', NULL, '', '2013-05-21 13:28:04', NULL, 86400, 1, '2013-05-21 13:28:04', '', '', @pluginId);
EOD;

        $this->addSql($sql);
    }
}
