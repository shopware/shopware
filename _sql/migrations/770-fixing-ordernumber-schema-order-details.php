<?php

class Migrations_Migration770 extends Shopware\Framework\Migration\AbstractMigration
{
    public function up($modus)
    {
        // s_order_details
        $sql = <<<'EOD'
            ALTER TABLE `s_order_details`
            MODIFY COLUMN `ordernumber` varchar(255) NOT NULL,
            MODIFY COLUMN `articleordernumber` varchar(255) NOT NULL;
EOD;
        $this->addSql($sql);
    }
}
