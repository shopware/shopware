<?php

class Migrations_Migration787 extends Shopware\Framework\Migration\AbstractMigration
{
    /**
     * @param string $modus
     * @return void
     */
    public function up($modus)
    {
        $sql = <<<'EOD'
        SET @elementId = (SELECT id FROM `s_core_config_elements` WHERE `name` = 'displayprofiletitle' LIMIT 1);
EOD;
        $this->addSql($sql);

        $sql = <<<'EOD'
        INSERT IGNORE INTO `s_core_config_element_translations` (`element_id`, `locale_id`, `label`, `description`)
        VALUES (@elementId, 2, 'Show title field', NULL);
EOD;
        $this->addSql($sql);
    }
}
