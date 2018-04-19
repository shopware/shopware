<?php
class Migrations_Migration380 Extends Shopware\Framework\Migration\AbstractMigration
{
    public function up($modus)
    {
        $sql = <<<'EOD'
        ALTER TABLE `s_user_billingaddress` MODIFY `street` VARCHAR(255);
EOD;
        $this->addSql($sql);
    }
}
