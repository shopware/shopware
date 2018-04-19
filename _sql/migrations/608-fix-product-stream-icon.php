<?php

class Migrations_Migration608 extends Shopware\Framework\Migration\AbstractMigration
{
    public function up($modus)
    {
        $sql = <<<'EOD'
UPDATE `s_core_menu` SET `class` = 'sprite-product-streams' WHERE `controller` = 'ProductStream';
EOD;
        $this->addSql($sql);

        return $sql;
    }
}
