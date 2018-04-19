<?php
class Migrations_Migration216 Extends Shopware\Framework\Migration\AbstractMigration
{
    public function up($modus)
    {
        $sql = <<<'EOD'
        ALTER TABLE `s_order_billingaddress`
            CHANGE `streetnumber` `streetnumber` VARCHAR( 50 ) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
            CHANGE `zipcode` `zipcode` VARCHAR( 50 ) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL;
EOD;
        $this->addSql($sql);
    }
}
