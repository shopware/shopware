<?php
class Migrations_Migration625 extends Shopware\Framework\Migration\AbstractMigration
{
    public function up($modus)
    {
        $this->updateProductMenu();
    }

    public function updateProductMenu()
    {
        $sql = <<<'EOD'
UPDATE `s_core_menu` SET `controller` = 'Feedback'
WHERE `controller` = 'BetaFeedback'
EOD;
        $this->addSql($sql);
    }
}
