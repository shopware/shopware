<?php
class Migrations_Migration210 Extends Shopware\Framework\Migration\AbstractMigration
{
    public function up($modus)
    {
        $sql = <<<'EOD'
        ALTER TABLE `s_blog` ADD `meta_title` VARCHAR( 255 ) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL;
EOD;
        $this->addSql($sql);
    }
}
