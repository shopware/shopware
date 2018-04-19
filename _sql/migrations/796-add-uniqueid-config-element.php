<?php

class Migrations_Migration796 extends Shopware\Framework\Migration\AbstractMigration
{
    public function up($modus)
    {
        $sql = <<<SQL
INSERT INTO s_core_config_elements (form_id, name, value, label, description, type, required, position, scope)
VALUES ('0', 'trackingUniqueId', 's:0:"";', 'Unique identifier', '', 'text', '0', '0', '1')
SQL;

        $statement = $this->getConnection()->prepare("SELECT id FROM s_core_config_elements WHERE name = 'update-unique-id'");
        $statement->execute();
        $id = $statement->fetchColumn();

        if (!empty($id)) {
            $sql = 'UPDATE s_core_config_elements SET name="trackingUniqueId" WHERE id=' . $this->getConnection()->quote($id);
        }

        $this->addSql($sql);
    }
}
