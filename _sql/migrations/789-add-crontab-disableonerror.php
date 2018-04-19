<?php

class Migrations_Migration789 extends Shopware\Framework\Migration\AbstractMigration
{
    public function up($modus)
    {
        $sql = <<<'EOD'
    ALTER TABLE `s_crontab` ADD COLUMN `disable_on_error` TINYINT(1) NOT NULL DEFAULT 1 AFTER `active`;
EOD;
        $this->addSql($sql);
    }
}
