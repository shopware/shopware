<?php

class Migrations_Migration758 extends Shopware\Framework\Migration\AbstractMigration
{
    public function up($modus)
    {
        $this->addSql("SET @resourceID = (SELECT id FROM s_core_acl_resources WHERE name = 'attributes' LIMIT 1);");
        $this->addSql("DELETE FROM  s_core_acl_privileges WHERE resourceID = @resourceID AND name = 'read'");
    }
}
