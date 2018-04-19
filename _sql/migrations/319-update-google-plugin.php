<?php
class Migrations_Migration319 extends Shopware\Framework\Migration\AbstractMigration
{
    public function up($modus)
    {
        $sql = <<<'EOD'
UPDATE `s_core_plugins`
SET label = "Google Analytics (deprecated)",
description = '<h3>This plugin is no longer supported. Please use the new "Google Services" plugin instead, available on the community store.</h3>'
WHERE name = 'Google' AND author = 'shopware AG'
EOD;

        $this->addSql($sql);
    }
}
