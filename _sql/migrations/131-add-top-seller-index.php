<?php
class Migrations_Migration131 Extends Shopware\Framework\Migration\AbstractMigration
{
    public function up($modus)
    {
        $sql = <<<'EOD'
ALTER TABLE  `s_articles_top_seller_ro` ADD INDEX  `listing_query` (  `sales` ,  `article_id` );
EOD;
        $this->addSql($sql);
    }
}
