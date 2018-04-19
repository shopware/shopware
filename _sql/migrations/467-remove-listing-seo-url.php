<?php
class Migrations_Migration467 Extends Shopware\Framework\Migration\AbstractMigration
{
    public function up($modus)
    {
        $sql = <<<'SQL'
            DELETE FROM `s_core_rewrite_urls` WHERE `path` LIKE 'listing/';
SQL;
        $this->addSql($sql);

        $sql = <<<'SQL'
            UPDATE `s_core_config_elements` SET `value` = NULL WHERE `name` = 'seostaticurls';
SQL;
        $this->addSql($sql);
    }
}


