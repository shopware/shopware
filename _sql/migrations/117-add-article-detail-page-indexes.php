<?php
class Migrations_Migration117 Extends Shopware\Framework\Migration\AbstractMigration
{
    public function up($modus)
    {
        $sql = <<<'EOD'
ALTER TABLE `s_articles_categories_ro` ADD INDEX  `category_id_by_article_id` (  `articleID` ,  `id` );
EOD;

        $this->addSql($sql);
    }
}
