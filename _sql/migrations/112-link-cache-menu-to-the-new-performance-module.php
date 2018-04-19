<?php
class Migrations_Migration112 Extends Shopware\Framework\Migration\AbstractMigration
{
    public function up($modus)
    {
        $sql = <<<'EOD'
UPDATE `s_core_menu` SET `controller` = 'Performance' WHERE `controller` = 'Cache';
EOD;

        $this->addSql($sql);
    }
}
