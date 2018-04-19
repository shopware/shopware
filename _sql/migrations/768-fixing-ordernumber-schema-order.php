<?php

class Migrations_Migration768 extends Shopware\Framework\Migration\AbstractMigration
{
    public function up($modus)
    {
        // s_order
        $sql = <<<'EOD'
            ALTER TABLE `s_order`
            MODIFY COLUMN `ordernumber` varchar(255);
EOD;
        $this->addSql($sql);
    }
}
