<?php

class Migrations_Migration632 extends Shopware\Framework\Migration\AbstractMigration
{
    public function up($modus)
    {
        $sql = <<<EOD
            UPDATE `s_core_config_elements` SET `label` = 'Abverkaufsartikel ohne Lagerbestand ausblenden' WHERE `s_core_config_elements`.`name` = 'hideNoInStock';
EOD;
        $this->addSql($sql);
    }
}
