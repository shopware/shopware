<?php
class Migrations_Migration397 Extends Shopware\Framework\Migration\AbstractMigration
{
    public function up($modus)
    {
        $sql = <<<'EOD'
            ALTER TABLE `s_cms_support` ADD `meta_title` VARCHAR( 255 ) NULL AFTER `text2` ,
            ADD `meta_keywords` VARCHAR( 255 ) NULL AFTER `meta_title` ,
            ADD `meta_description` TEXT NULL AFTER `meta_keywords`;
EOD;
        $this->addSql($sql);
    }
}
