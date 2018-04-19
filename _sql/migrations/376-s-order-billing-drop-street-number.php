<?php
class Migrations_Migration376 Extends Shopware\Framework\Migration\AbstractMigration
{
    public function up($modus)
    {
        $sql = <<<'EOD'
        ALTER TABLE `s_order_billingaddress` DROP `streetnumber`;
EOD;
        $this->addSql($sql);
    }
}
