<?php

class Migrations_Migration771 extends Shopware\Framework\Migration\AbstractMigration
{
    public function up($modus)
    {
        // s_order_notes
        $sql = <<<'EOD'
            ALTER TABLE `s_order_notes`
            MODIFY COLUMN `ordernumber` varchar(255) NOT NULL;
EOD;
        $this->addSql($sql);
    }
}
