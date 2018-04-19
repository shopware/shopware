<?php

class Migrations_Migration900 extends Shopware\Framework\Migration\AbstractMigration
{
    public function up($modus)
    {
        $this->removeLastArticlesPlugin();
        $this->cleanupLastArticlesTable();
        $this->renameConfigElementKeys();
    }

    private function cleanupLastArticlesTable()
    {
        $this->addSql("ALTER TABLE `s_emarketing_lastarticles` DROP `img`, DROP `name`;");
    }

    private function removeLastArticlesPlugin()
    {
        $this->addSql('DELETE FROM s_core_plugins WHERE `name` = "LastArticles"');
        $this->addSql('UPDATE s_core_config_forms SET plugin_id = NULL WHERE name = "LastArticles";');
    }

    private function renameConfigElementKeys()
    {
        $this->addSql("SET @formId = (SELECT id FROM `s_core_config_forms` WHERE `name` = 'LastArticles' LIMIT 1);");
        $this->addSql("UPDATE s_core_config_elements SET `name` = 'lastarticles_show' WHERE form_id = @formId AND `name` = 'show'");
        $this->addSql("UPDATE s_core_config_elements SET `name` = 'lastarticles_controller' WHERE form_id = @formId AND `name` = 'controller'");
        $this->addSql("UPDATE s_core_config_elements SET `name` = 'lastarticles_time' WHERE form_id = @formId AND `name` = 'time'");
    }
}
