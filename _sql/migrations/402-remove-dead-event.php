<?php
class Migrations_Migration402 Extends Shopware\Framework\Migration\AbstractMigration
{
    public function up($modus)
    {
        $sql = <<<'EOD'
DELETE FROM s_core_subscribes WHERE listener LIKE "Shopware_Plugins_Core_Log_Bootstrap::onInitResourceLog"
EOD;

        $this->addSql($sql);
    }
}
