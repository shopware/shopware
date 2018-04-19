<?php
class Migrations_Migration202 Extends Shopware\Framework\Migration\AbstractMigration
{
    public function up($modus)
    {
        $sql = <<<'EOD'
            DELETE FROM s_core_subscribes
            WHERE subscribe = 'Enlight_Controller_Router_Assemble'
            AND listener = 'Shopware_Plugins_Frontend_RouterOld_Bootstrap::onAssemble'
EOD;
        $this->addSql($sql);
    }
}
