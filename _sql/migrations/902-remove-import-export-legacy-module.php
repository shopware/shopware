<?php

class Migrations_Migration902 extends Shopware\Framework\Migration\AbstractMigration
{
    public function up($modus)
    {
        $this->removeMenuEntry();
        $this->removeAclPermissions();
    }

    private function removeMenuEntry()
    {
        $this->addSql("DELETE FROM s_core_menu WHERE name LIKE 'Import/Export' AND controller LIKE 'ImportExport' LIMIT 1");
    }

    private function removeAclPermissions()
    {
        $this->addSql("DELETE r, p FROM s_core_acl_resources r JOIN s_core_acl_privileges p ON p.resourceID = r.id WHERE r.name = 'importexport'");
    }
}
