<?php
class Migrations_Migration110 Extends Shopware\Framework\Migration\AbstractMigration
{
    public function up($modus)
    {
        $sql = <<<'EOD'
ALTER TABLE  `s_core_auth` ADD  `disabled_cache` TINYINT( 1 ) UNSIGNED NOT NULL DEFAULT  '0';
EOD;

        $this->addSql($sql);
    }
}
