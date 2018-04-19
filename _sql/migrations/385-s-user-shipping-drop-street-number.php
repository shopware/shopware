<?php
class Migrations_Migration385 Extends Shopware\Framework\Migration\AbstractMigration
{
    public function up($modus)
    {
        $sql = <<<'EOD'
        ALTER TABLE `s_user_shippingaddress` DROP `streetnumber`;
EOD;
        $this->addSql($sql);
    }
}
