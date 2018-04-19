<?php
class Migrations_Migration330 Extends Shopware\Framework\Migration\AbstractMigration
{
    public function up($modus)
    {
        $sql = <<<'EOD'
        ALTER TABLE `s_core_plugins` ADD `capability_secure_uninstall` int(1) NOT NULL DEFAULT 0;
EOD;
        $this->addSql($sql);
    }
}
