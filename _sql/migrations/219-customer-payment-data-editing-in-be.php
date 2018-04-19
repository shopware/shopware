<?php
class Migrations_Migration219 Extends Shopware\Framework\Migration\AbstractMigration
{
    public function up($modus)
    {
        $sql = <<<'EOD'
        INSERT IGNORE INTO `s_core_payment_data` (payment_mean_id, user_id, bankname, account_number, bank_code, account_holder, created_at)
        SELECT (SELECT id FROM s_core_paymentmeans WHERE name LIKE 'debit') as payment_mean_id, s_user_debit.userID as user_id,
        s_user_debit.bankname as bankname, s_user_debit.account as number,
        s_user_debit.bankcode as bank_code, s_user_debit.bankholder as account_holder,
        NOW() as created_at
        FROM s_user_debit;

        SET @plugin_id = (SELECT id FROM s_core_plugins WHERE name = 'PaymentMethods' LIMIT 1);

        INSERT IGNORE INTO `s_core_subscribes` (`id`, `subscribe`, `type`, `listener`, `pluginID`, `position`) VALUES
            (NULL, 'Enlight_Controller_Action_PostDispatchSecure_Backend_Customer', 0, 'Shopware_Plugins_Core_PaymentMethods_Bootstrap::onBackendCustomerPostDispatch', @plugin_id, 0);

EOD;
        $this->addSql($sql);
    }
}
