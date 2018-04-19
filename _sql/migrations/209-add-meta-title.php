<?php
class Migrations_Migration209 Extends Shopware\Framework\Migration\AbstractMigration
{
    public function up($modus)
    {
        $sql = <<<'EOD'
        ALTER TABLE `s_articles` ADD `metaTitle` VARCHAR( 255 ) CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL AFTER `topseller`;
EOD;
        $this->addSql($sql);
    }
}
