<?php

class Migrations_Migration499 Extends Shopware\Framework\Migration\AbstractMigration
{
    public function up($modus)
    {
        // Add new ACL resource 'swagupdate'
        $sql = <<<'EOD'
            INSERT IGNORE INTO s_core_acl_resources (name) VALUES ('swagupdate');
EOD;
        $this->addSql($sql);

        // Add new ACL resource 'swagupdate'
        $sql = <<<'EOD'
            SET @resourceId = (SELECT id FROM s_core_acl_resources WHERE name = 'swagupdate' LIMIT 1);
EOD;
        $this->addSql($sql);

        // Add new ACL privileges corresponding to the ACL resource 'swagupdate'
        $sql = <<<'EOD'
            INSERT IGNORE INTO s_core_acl_privileges (resourceID,name) VALUES (@resourceId, 'read');
            INSERT IGNORE INTO s_core_acl_privileges (resourceID,name) VALUES (@resourceId, 'update');
            INSERT IGNORE INTO s_core_acl_privileges (resourceID,name) VALUES (@resourceId, 'skipUpdate');
EOD;
        $this->addSql($sql);

        // Reference the 'theme' ACL resource to the menu entry
        $sql = <<<'EOD'
            UPDATE s_core_menu SET resourceID = @resourceId WHERE controller = 'SwagUpdate';
EOD;
        $this->addSql($sql);
    }
}