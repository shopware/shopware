<?php

class Migrations_Migration790 extends Shopware\Framework\Migration\AbstractMigration
{
    public function up($modus)
    {
        $this->addSql("SET @elementId = (SELECT id FROM s_core_config_elements WHERE name='no_order_mail');");
        $this->addSql("UPDATE s_core_config_elements SET label='Bestellbestätigung an Shopbetreiber deaktivieren' WHERE id = @elementId;");
        $this->addSql("UPDATE s_core_config_element_translations SET label='Disable order confirmation to shop owner' WHERE element_id = @elementId AND locale_id = 2;");
    }
}
