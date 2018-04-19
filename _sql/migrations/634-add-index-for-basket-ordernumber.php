<?php

class Migrations_Migration634 extends Shopware\Framework\Migration\AbstractMigration
{
    public function up($modus)
    {
        $sql = <<<SQL
ALTER TABLE `s_order_basket`
ADD INDEX `ordernumber` (`ordernumber`);
SQL;

        $this->addSql($sql);
    }
}
