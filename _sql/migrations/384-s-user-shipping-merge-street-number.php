<?php
class Migrations_Migration384 Extends Shopware\Framework\Migration\AbstractMigration
{
    public function up($modus)
    {
        $sql = <<<'EOD'
        UPDATE s_user_shippingaddress SET street = CONCAT(street, ' ', streetnumber);
EOD;
        $this->addSql($sql);
    }
}
