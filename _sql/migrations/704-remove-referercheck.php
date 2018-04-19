<?php

class Migrations_Migration704 extends Shopware\Framework\Migration\AbstractMigration
{
    public function up($modus)
    {
        $this->addSql("DELETE elems, translations FROM `s_core_config_elements` elems INNER JOIN `s_core_config_element_translations` translations ON elems.id = translations.element_id WHERE `name` = 'refererCheck';");
    }
}
