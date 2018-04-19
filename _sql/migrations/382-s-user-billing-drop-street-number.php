<?php
class Migrations_Migration382 Extends Shopware\Framework\Migration\AbstractMigration
{
    public function up($modus)
    {
        $sql = <<<'EOD'
        ALTER TABLE `s_user_billingaddress` DROP `streetnumber`;
EOD;
        $this->addSql($sql);
    }
}
