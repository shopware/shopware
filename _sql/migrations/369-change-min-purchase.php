<?php
class Migrations_Migration369 Extends Shopware\Framework\Migration\AbstractMigration
{
    public function up($modus)
    {
        $sql = <<<'EOD'
        ALTER TABLE `s_articles_details` CHANGE `minpurchase` `minpurchase` INT(11) UNSIGNED NOT NULL DEFAULT '1';
        UPDATE s_articles_details SET minpurchase = 1 WHERE minpurchase = 0;
EOD;
        $this->addSql($sql);
    }
}
