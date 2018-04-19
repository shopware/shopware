<?php
class Migrations_Migration363 Extends Shopware\Framework\Migration\AbstractMigration
{
    public function up($modus)
    {
        $sql = <<<'EOD'
        ALTER TABLE `s_user_shippingaddress` ADD `additional_address_line1` VARCHAR( 255 ) CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL ,
        ADD `additional_address_line2` VARCHAR( 255 ) CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL;
EOD;
        $this->addSql($sql);
    }
}



