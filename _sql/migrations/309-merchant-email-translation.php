<?php
class Migrations_Migration309 Extends Shopware\Framework\Migration\AbstractMigration
{
    public function up($modus)
    {
        $sql = <<<'EOD'
            UPDATE s_cms_static SET description = 'Merchant login' WHERE description = 'Reseller-Login';

            UPDATE `s_core_translations` SET `objectdata` = 'a:2:{s:7:"subject";s:39:"Your merchant account has been unlocked";s:7:"content";s:186:"Hello,\n\nYour merchant account {config name=shopName} has been unlocked.\n  \nFrom now on, we will charge you the net purchase price. \n  \nBest regards\n  \nYour team of {config name=shopName}";}'
            WHERE `objectdata` = 'a:2:{s:7:"subject";s:37:"Your trader account has been unlocked";s:7:"content";s:184:"Hello,\n\nYour trader account {config name=shopName} has been unlocked.\n  \nFrom now on, we will charge you the net purchase price. \n  \nBest regards\n  \nYour team of {config name=shopName}";}';

            UPDATE `s_core_translations` SET `objectdata` = 'a:2:{s:7:"subject";s:43:"Your merchant account has not been accepted";s:7:"content";s:309:"Dear customer,\n\nThank you for your interest in our trade prices. Unfortunately, we do not have a trading license yet so that we cannot accept you as a merchant. \n\nIn case of further questions please do not hesitate to contact us via telephone, fax or email. \n\nBest regards\n\nYour Team of {config name=shopName}";}'
            WHERE `objectdata` = 'a:2:{s:7:"subject";s:40:"Your trader acount has not been accepted";s:7:"content";s:306:"Dear customer,\n\nThank you for your interest in our trade prices. Unfortunately, we do not have a trading license yet so that we cannot accept you as a trader. \n\nIn case of further questions please do not heitate to contact us via telephone, fax or email. \n\nBest regards\n\nYour Team of {config name=shopName}";}';

EOD;
        $this->addSql($sql);
    }
}
