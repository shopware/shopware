<?php

class Migrations_Migration766 extends Shopware\Framework\Migration\AbstractMigration
{
    public function up($modus)
    {
        // s_articles_details
        $sql = <<<'EOD'
            ALTER TABLE `s_articles_details`
            MODIFY COLUMN `ordernumber` varchar(255) NOT NULL;
EOD;
        $this->addSql($sql);
    }
}
