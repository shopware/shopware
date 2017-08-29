<?php
class Migrations_Migration224 Extends Shopware\Framework\Migration\AbstractMigration
{
    public function up($modus)
    {
        $sql = <<<'EOD'
            CREATE TABLE IF NOT EXISTS `s_statistics_article_impression` (
              `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
              `articleId` int(11) unsigned NOT NULL,
              `shopId` int(11) unsigned NOT NULL,
              `date` date NOT NULL DEFAULT '0000-00-00',
              `impressions` int(11) NOT NULL,
              PRIMARY KEY (`id`),
              UNIQUE KEY `articleId_2` (`articleId`,`shopId`,`date`),
              KEY `articleId` (`articleId`)
            ) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci AUTO_INCREMENT=1 ;
EOD;
        $this->addSql($sql);
    }
}
