<?php
class Migrations_Migration367 extends Shopware\Framework\Migration\AbstractMigration
{
    public function up($modus)
    {
        $statement = $this->getConnection()->query(
            "SHOW INDEX FROM s_statistics_article_impression WHERE KEY_NAME = 'articleId_2'"
        );
        $data = $statement->fetchAll();

        if (!empty($data)) {
            $this->addSql(
                'ALTER TABLE `s_statistics_article_impression`
            DROP KEY `articleId_2`;'
            );
        }

        $sql = <<<'EOD'
        ALTER TABLE `s_order` ADD `deviceType` VARCHAR(50) NOT NULL DEFAULT 'desktop';
        ALTER TABLE `s_statistics_visitors` ADD `deviceType` VARCHAR(50) NOT NULL DEFAULT 'desktop';
        ALTER TABLE `s_statistics_currentusers` ADD `deviceType` VARCHAR(50) NOT NULL DEFAULT 'desktop';
        ALTER TABLE `s_statistics_article_impression`
            ADD `deviceType` VARCHAR(50) NOT NULL DEFAULT 'desktop',
            ADD UNIQUE KEY `articleId_2` (`articleId`,`shopId`,`date`, `deviceType`);
EOD;
        $this->addSql($sql);
    }
}



