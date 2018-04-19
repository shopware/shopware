<?php

class Migrations_Migration738 extends Shopware\Framework\Migration\AbstractMigration
{
    /**
     * @param string $modus
     * @return void
     */
    public function up($modus)
    {
        $sql = <<<SQL
UPDATE `s_library_component` SET `x_type` = 'emotion-components-iframe' WHERE `name` = 'iFrame-Element'
SQL;

        $this->addSql($sql);
    }
}
