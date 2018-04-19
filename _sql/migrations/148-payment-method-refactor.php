<?php
class Migrations_Migration148 Extends Shopware\Framework\Migration\AbstractMigration
{
    public function up($modus)
    {
        $sql = <<<'EOD'
        INSERT IGNORE INTO  `s_core_plugins` (`id`, `namespace`, `name`, `label`, `source`, `description`, `description_long`, `active`, `added`, `installation_date`, `update_date`, `refresh_date`, `author`, `copyright`, `license`, `version`, `support`, `changes`, `link`, `store_version`, `store_date`, `capability_update`, `capability_install`, `capability_enable`, `capability_dummy`, `update_source`, `update_version`) VALUES
            (NULL, 'Core', 'PaymentMethods', 'PaymentMethods', 'Default', NULL, NULL, 1, '2013-10-30 08:12:22', '2013-10-30 08:13:26', '2013-10-30 08:13:26', '2013-10-30 08:13:34', 'shopware AG', 'Copyright © 2013, shopware AG', NULL, '1.0.0', NULL, NULL, NULL, NULL, NULL, 1, 0, 0, 0, NULL, NULL);

        SET @plugin_id = (SELECT id FROM s_core_plugins WHERE name = 'PaymentMethods' LIMIT 1);

        INSERT IGNORE INTO `s_core_subscribes` (`id`, `subscribe`, `type`, `listener`, `pluginID`, `position`) VALUES
            (NULL, 'Shopware_Modules_Admin_InitiatePaymentClass_AddClass', 0, 'Shopware_Plugins_Core_PaymentMethods_Bootstrap::addPaymentClass', @plugin_id, 0),
            (NULL, 'Enlight_Controller_Action_PostDispatchSecure', 0, 'Shopware_Plugins_Core_PaymentMethods_Bootstrap::addPaths', @plugin_id, 0),
            (NULL, 'Enlight_Controller_Action_PostDispatchSecure_Backend_Order', 0, 'Shopware_Plugins_Core_PaymentMethods_Bootstrap::onBackendOrderPostDispatch', @plugin_id, 0);

        INSERT IGNORE INTO `s_core_snippets` (`id`, `namespace`, `shopID`, `localeID`, `name`, `value`, `created`, `updated`)
            SELECT NULL, 'engine/Shopware/Plugins/Default/Core/PaymentMethods/Views/frontend/plugins/payment/debit', `shopID`, `localeID`, `name`, `value`, '2013-11-01 00:00:00', '2013-11-01 00:00:00'
            FROM `s_core_snippets`
            WHERE `s_core_snippets`.`namespace` LIKE 'frontend/plugins/payment/debit';
EOD;
        $this->addSql($sql);
    }
}
