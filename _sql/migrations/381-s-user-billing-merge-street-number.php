<?php
class Migrations_Migration381 Extends Shopware\Framework\Migration\AbstractMigration
{
    public function up($modus)
    {
        $sql = <<<'EOD'
        UPDATE s_user_billingaddress SET street = CONCAT(street, ' ', streetnumber);
EOD;
        $this->addSql($sql);
    }
}
