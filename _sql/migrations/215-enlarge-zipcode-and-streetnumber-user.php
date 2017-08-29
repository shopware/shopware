<?php
class Migrations_Migration215 Extends Shopware\Framework\Migration\AbstractMigration
{
    public function up($modus)
    {
        $sql = <<<'EOD'
        ALTER TABLE `s_user_billingaddress`
            CHANGE `streetnumber` `streetnumber` VARCHAR( 50 ) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
            CHANGE `zipcode` `zipcode` VARCHAR( 50 ) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL;

        ALTER TABLE `s_user_shippingaddress`
            CHANGE `streetnumber` `streetnumber` VARCHAR( 50 ) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
            CHANGE `zipcode` `zipcode` VARCHAR( 50 ) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL;
EOD;
        $this->addSql($sql);
    }
}
