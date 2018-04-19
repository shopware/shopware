<?php
class Migrations_Migration465 Extends Shopware\Framework\Migration\AbstractMigration
{
    public function up($modus)
    {
        $sql = "
            INSERT INTO `s_core_config_elements` (`form_id`, `name`, `value`, `label`, `type`, `required`, `position`, `scope`)
            VALUES ('0', 'tokenSecret', 's:0:\"\"', 'Secret für die API Kommunikation', 'text', '0', '0', '0');
        ";
        $this->addSql($sql);
    }
}
