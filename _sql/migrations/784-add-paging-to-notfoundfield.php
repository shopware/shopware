<?php

class Migrations_Migration784 extends Shopware\Framework\Migration\AbstractMigration
{
    public function up($modus)
    {
        $data = [
            'store' => 'base.PageNotFoundDestinationOptions',
            'displayField' => 'name',
            'valueField' => 'id',
            'allowBlank' => false,
            'pageSize' => 25
        ];

        $sql = sprintf("UPDATE s_core_config_elements SET `options` = '%s' WHERE `name` = 'PageNotFoundDestination'", serialize($data));
        $this->addSql($sql);
    }
}
