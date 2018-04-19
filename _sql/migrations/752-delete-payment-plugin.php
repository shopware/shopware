<?php
class Migrations_Migration752 extends Shopware\Framework\Migration\AbstractMigration
{
    /**
     * @param string $modus
     * @return void
     */
    public function up($modus)
    {
        $sql = <<<'EOD'
DELETE FROM `s_core_plugins` WHERE name = "Payment";
EOD;
        $this->addSql($sql);

        $sql = <<<'EOD'
DELETE FROM `s_core_subscribes` WHERE listener = "Shopware_Plugins_Frontend_Payment_Bootstrap::onInitResourcePayments";
EOD;
        $this->addSql($sql);
    }
}
