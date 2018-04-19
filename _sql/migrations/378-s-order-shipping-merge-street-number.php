<?php
class Migrations_Migration378 Extends Shopware\Framework\Migration\AbstractMigration
{
    public function up($modus)
    {
        $sql = <<<'EOD'
        UPDATE s_order_shippingaddress SET street = CONCAT(street, ' ', streetnumber);
EOD;
        $this->addSql($sql);
    }
}
