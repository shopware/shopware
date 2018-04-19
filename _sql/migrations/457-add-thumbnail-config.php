<?php
class Migrations_Migration457 Extends Shopware\Framework\Migration\AbstractMigration
{
    public function up($modus)
    {

        // add new config form media
        $sql = <<<'EOD'
        SET @parent = (SELECT id FROM s_core_config_forms WHERE name = 'Frontend' AND label = 'Storefront' LIMIT 1);
EOD;
        $this->addSql($sql);


        $sql = <<<'EOD'
            INSERT IGNORE INTO `s_core_config_forms` (`parent_id`, `name`, `label`, `description`, `position`, `scope`, `plugin_id`)
            VALUES (@parent, 'Media', 'Medien', NULL, '13', '', NULL);
EOD;
        $this->addSql($sql);

        $sql = <<<'EOD'
        SET @parent = (SELECT id FROM s_core_config_forms WHERE name = 'Media' AND label = 'Medien' LIMIT 1);
EOD;
        $this->addSql($sql);


        $sql = <<<'EOD'
            INSERT IGNORE INTO `s_core_config_form_translations` (`form_id`, `locale_id`, `label`, `description`)
            VALUES (@parent, 2, 'Media', '');
EOD;
        $this->addSql($sql);

        // add a new config field
        $sql = <<<'EOD'
            INSERT IGNORE INTO `s_core_config_elements` (`form_id`, `name`, `value`, `label`, `description`, `type`, `required`, `position`, `scope`, `filters`, `validators`, `options`) VALUES
            (@parent, 'thumbnailNoiseFilter', 'b:0;', 'Rauschfilterung bei Thumbnails', 'Filtert beim Generieren der Thumbnails Bildfehler heraus. Achtung! Bei aktivierter Option kann das Generieren der Thumbnails wesentlich länger dauern', 'checkbox', 0, 0, 0, NULL, NULL, 'a:0:{}');
EOD;
        $this->addSql($sql);

        $sql = <<<'EOD'
        SET @elementId = (SELECT id FROM `s_core_config_elements` WHERE `name` = 'thumbnailNoiseFilter' LIMIT 1);
EOD;
        $this->addSql($sql);

        $sql = <<<'EOD'
        INSERT IGNORE INTO `s_core_config_element_translations` (`element_id`, `locale_id`, `label`, `description`)
        VALUES (@elementId, '2', 'Thumbnail noise filter', 'Produces clearer thumbnails. May increase thumbnail generation time.');
EOD;
        $this->addSql($sql);
    }
}



