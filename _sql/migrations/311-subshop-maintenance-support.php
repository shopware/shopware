<?php
class Migrations_Migration311 Extends Shopware\Framework\Migration\AbstractMigration
{
    public function up($modus)
    {
        $sql = <<<'EOD'
UPDATE s_core_config_elements SET scope = 1 WHERE name = 'setoffline' OR name = 'offlineip';
EOD;
        $this->addSql($sql);
    }
}
