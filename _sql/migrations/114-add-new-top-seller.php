<?php
class Migrations_Migration114 Extends Shopware\Framework\Migration\AbstractMigration
{
    public function up($modus)
    {
        $sql = <<<'EOD'
INSERT INTO `s_core_plugins` (`id`, `namespace`, `name`, `label`, `source`, `description`, `description_long`, `active`, `added`, `installation_date`, `update_date`, `refresh_date`, `author`, `copyright`, `license`, `version`, `support`, `changes`, `link`, `store_version`, `store_date`, `capability_update`, `capability_install`, `capability_enable`, `update_source`, `update_version`) VALUES
(NULL, 'Core', 'MarketingAggregate', 'Shopware Marketing Aggregat Funktionen', 'Default', NULL, NULL, 1, '2013-04-30 14:19:13', '2013-04-30 14:26:48', '2013-04-30 14:26:48', '2013-04-30 14:26:51', 'shopware AG', 'Copyright © 2012, shopware AG', NULL, '1.0.0', NULL, NULL, 'http://www.shopware.de/', NULL, NULL, 1, 1, 1, NULL, NULL);

SET @pluginId = (SELECT id FROM s_core_plugins WHERE name = 'MarketingAggregate');

INSERT INTO `s_core_subscribes` (`id`, `subscribe`, `type`, `listener`, `pluginID`, `position`) VALUES
(NULL, 'Shopware_Modules_Order_SaveOrder_ProcessDetails', 0, 'Shopware_Plugins_Core_MarketingAggregate_Bootstrap::incrementTopSeller', @pluginId, 0),
(NULL, 'Shopware_Modules_Articles_GetArticleCharts', 0, 'Shopware_Plugins_Core_MarketingAggregate_Bootstrap::afterTopSellerSelected', @pluginId, 0),
(NULL, 'Enlight_Bootstrap_InitResource_TopSeller', 0, 'Shopware_Plugins_Core_MarketingAggregate_Bootstrap::initTopSellerResource', @pluginId, 0),
(NULL, 'Enlight_Controller_Action_Backend_Config_InitTopSeller', 0, 'Shopware_Plugins_Core_MarketingAggregate_Bootstrap::initTopSeller', @pluginId, 0),
(NULL, 'Enlight_Controller_Dispatcher_ControllerPath_Backend_TopSeller', 0, 'Shopware_Plugins_Core_MarketingAggregate_Bootstrap::getTopSellerBackendController', @pluginId, 0),
(NULL, 'Shopware_CronJob_RefreshTopSeller', 0, 'Shopware_Plugins_Core_MarketingAggregate_Bootstrap::refreshTopSeller', @pluginId, 0),
(NULL, 'Shopware\\Models\\Article\\Article::postUpdate', 0, 'Shopware_Plugins_Core_MarketingAggregate_Bootstrap::refreshArticle', @pluginId, 0),
(NULL, 'Shopware\\Models\\Article\\Article::postPersist', 0, 'Shopware_Plugins_Core_MarketingAggregate_Bootstrap::refreshArticle', @pluginId, 0),
(NULL, 'Shopware_Modules_Order_SaveOrder_ProcessDetails', 0, 'Shopware_Plugins_Core_MarketingAggregate_Bootstrap::addNewAlsoBought', @pluginId, 0),
(NULL, 'Enlight_Controller_Dispatcher_ControllerPath_Backend_AlsoBought', 0, 'Shopware_Plugins_Core_MarketingAggregate_Bootstrap::getAlsoBoughtBackendController', @pluginId, 0),
(NULL, 'Enlight_Bootstrap_InitResource_AlsoBought', 0, 'Shopware_Plugins_Core_MarketingAggregate_Bootstrap::initAlsoBoughtResource', @pluginId, 0),
(NULL, 'Enlight_Controller_Dispatcher_ControllerPath_Backend_SimilarShown', 0, 'Shopware_Plugins_Core_MarketingAggregate_Bootstrap::getSimilarShownBackendController', @pluginId, 0),
(NULL, 'Enlight_Bootstrap_InitResource_SimilarShown', 0, 'Shopware_Plugins_Core_MarketingAggregate_Bootstrap::initSimilarShownResource', @pluginId, 0),
(NULL, 'Shopware_Modules_Marketing_GetSimilarShownArticles', 0, 'Shopware_Plugins_Core_MarketingAggregate_Bootstrap::afterSimilarShownArticlesSelected', @pluginId, 0),
(NULL, 'Shopware_Plugins_LastArticles_ResetLastArticles', 0, 'Shopware_Plugins_Core_MarketingAggregate_Bootstrap::afterSimilarShownArticlesReset', @pluginId, 0),
(NULL, 'Shopware_Modules_Articles_Before_SetLastArticle', 0, 'Shopware_Plugins_Core_MarketingAggregate_Bootstrap::beforeSetLastArticle', @pluginId, 0),
(NULL, 'Shopware_CronJob_RefreshSimilarShown', 0, 'Shopware_Plugins_Core_MarketingAggregate_Bootstrap::refreshSimilarShown', @pluginId, 0);

INSERT INTO `s_crontab` (`id`, `name`, `action`, `elementID`, `data`, `next`, `start`, `interval`, `active`, `end`, `inform_template`, `inform_mail`, `pluginID`) VALUES
(NULL, 'Topseller Refresh', 'RefreshTopSeller', NULL, '', '2013-05-21 14:29:44', NULL, 86400, 1, '2013-05-21 14:29:44', '', '', @pluginId),
(NULL, 'Similar shown article refresh', 'RefreshSimilarShown', NULL, '', '2013-05-21 14:29:44', NULL, 86400, 1, '2013-05-21 14:29:44', '', '', @pluginId);


CREATE TABLE IF NOT EXISTS `s_articles_also_bought_ro` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `article_id` int(11) unsigned NOT NULL,
  `related_article_id` int(11) NOT NULL,
  `sales` int(11) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `bought_combination` (`article_id`,`related_article_id`),
  KEY `related_article_id` (`related_article_id`),
  KEY `article_id` (`article_id`),
  KEY `get_also_bought_articles` (`article_id`,`sales`,`related_article_id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1 ;

CREATE TABLE IF NOT EXISTS `s_articles_similar_shown_ro` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `article_id` int(11) unsigned NOT NULL,
  `related_article_id` int(11) NOT NULL,
  `viewed` int(11) unsigned NOT NULL DEFAULT '0',
  `init_date` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE  `viewed_combination` (  `article_id` ,  `related_article_id` ),
  KEY `viewed` (`viewed`,`related_article_id`,`article_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1 ;

CREATE TABLE IF NOT EXISTS `s_articles_top_seller_ro` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `article_id` int(11) unsigned NOT NULL,
  `sales` int(11) unsigned NOT NULL DEFAULT '0',
  `last_cleared` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `article_id` (`article_id`),
  KEY `sales` (`sales`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1 ;


ALTER TABLE  `s_emarketing_lastarticles` ADD INDEX  `get_last_articles` (  `sessionID` ,  `time` );
ALTER TABLE  `s_articles` ADD INDEX  `product_newcomer` (  `active` ,  `datum` );
ALTER TABLE  `s_articles_details` ADD INDEX  `get_similar_articles` (  `kind` ,  `sales` );
EOD;

        $this->addSql($sql);
    }
}
