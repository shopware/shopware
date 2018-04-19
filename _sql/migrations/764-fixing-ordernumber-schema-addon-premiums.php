<?php

class Migrations_Migration764 extends Shopware\Framework\Migration\AbstractMigration
{
    public function up($modus)
    {
        // s_addon_premiums
        $sql = <<<'EOD'
            ALTER TABLE `s_addon_premiums`
            MODIFY COLUMN `ordernumber` varchar(255) NOT NULL DEFAULT '0',
            MODIFY COLUMN `ordernumber_export` varchar(255) NOT NULL;
EOD;
        $this->addSql($sql);
    }
}
