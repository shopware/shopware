<?php

class Migrations_Migration498 Extends Shopware\Framework\Migration\AbstractMigration
{
    public function up($modus)
    {
        // Change the 'cache' ACL resource to 'performance'
        $sql = <<<'EOD'
            UPDATE s_core_acl_resources SET name = 'performance' WHERE name = 'cache';
EOD;
        $this->addSql($sql);

        // Add new ACL resource 'theme'
        $sql = <<<'EOD'
            INSERT IGNORE INTO s_core_acl_resources (name) VALUES ('theme');
EOD;
        $this->addSql($sql);

        // Add new ACL resource 'theme'
        $sql = <<<'EOD'
            SET @resourceId = (SELECT id FROM s_core_acl_resources WHERE name = 'theme' LIMIT 1);
EOD;
        $this->addSql($sql);

        // Add new ACL privileges corresponding to the ACL resource 'theme'
        $sql = <<<'EOD'
            INSERT IGNORE INTO s_core_acl_privileges (resourceID,name) VALUES (@resourceId, 'read');
            INSERT IGNORE INTO s_core_acl_privileges (resourceID,name) VALUES (@resourceId, 'preview');
            INSERT IGNORE INTO s_core_acl_privileges (resourceID,name) VALUES (@resourceId, 'changeTheme');
            INSERT IGNORE INTO s_core_acl_privileges (resourceID,name) VALUES (@resourceId, 'createTheme');
            INSERT IGNORE INTO s_core_acl_privileges (resourceID,name) VALUES (@resourceId, 'uploadTheme');
            INSERT IGNORE INTO s_core_acl_privileges (resourceID,name) VALUES (@resourceId, 'configureTheme');
            INSERT IGNORE INTO s_core_acl_privileges (resourceID,name) VALUES (@resourceId, 'configureSystem');
EOD;
        $this->addSql($sql);

        // Reference the 'theme' ACL resource to the menu entry
        $sql = <<<'EOD'
            UPDATE s_core_menu SET resourceID = @resourceId WHERE controller = 'Theme';
EOD;
        $this->addSql($sql);
    }
}