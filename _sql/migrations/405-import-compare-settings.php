<?php
class Migrations_Migration405 Extends Shopware\Framework\Migration\AbstractMigration
{
    public function up($modus)
    {
        /**
         * Get formId
         * Set plugin Id to NULL to be independent
         * Rename setting to be unique
         *
         * Get compare pluginId
         * remove compare plugin
         * remove subscribes of compare plugin
         */

        $sql = <<<'EOD'
            SET @configFormId = (SELECT id FROM s_core_config_forms WHERE name = 'Compare' LIMIT 1);
EOD;
        $this->addSql($sql);

        $sql = <<<'EOD'
            UPDATE s_core_config_forms SET plugin_id = NULL WHERE id = @configFormId;
EOD;
        $this->addSql($sql);

        $sql = <<<'EOD'
            UPDATE s_core_config_elements SET name = 'compareShow' WHERE form_id = @configFormId AND name = 'show';
EOD;
        $this->addSql($sql);

        $sql = <<<'EOD'
            SET @comparePluginId = (SELECT id FROM s_core_plugins WHERE name = 'Compare');
EOD;
        $this->addSql($sql);

        $sql = <<<'EOD'
            DELETE FROM s_core_plugins WHERE id = @comparePluginId;
EOD;
        $this->addSql($sql);

        $sql = <<<'EOD'
            DELETE FROM s_core_subscribes WHERE pluginID = @comparePluginId;
EOD;
        $this->addSql($sql);
    }
}