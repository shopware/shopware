<?php
class Migrations_Migration379 Extends Shopware\Framework\Migration\AbstractMigration
{
    public function up($modus)
    {
        $sql = <<<'EOD'
        ALTER TABLE `s_order_shippingaddress` DROP `streetnumber`;
EOD;
        $this->addSql($sql);
    }
}
