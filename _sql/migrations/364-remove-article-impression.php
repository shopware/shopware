<?php
class Migrations_Migration364 Extends Shopware\Framework\Migration\AbstractMigration
{
    public function up($modus)
    {
        $sql = <<<'EOD'
        ALTER TABLE `s_articles_details` DROP `impressions`;
EOD;
        $this->addSql($sql);
    }
}



