<?php
class Migrations_Migration447 Extends Shopware\Framework\Migration\AbstractMigration
{
    public function up($modus)
    {
        if ($modus !== \Shopware\Framework\Migration\AbstractMigration::MODUS_INSTALL) {
            return;
        }

        $this->addSql("SET @formID = (SELECT id FROM s_core_config_forms WHERE `name`='TagCloud');");
        $this->addSql("SET @elementID = (SELECT id FROM s_core_config_elements WHERE form_id=@formID AND `name`='controller');");

        $sql = <<<EOL
        UPDATE s_core_config_elements SET value = 's:7:"listing";' WHERE id = @elementID;
EOL;
        $this->addSql($sql);
    }
}
