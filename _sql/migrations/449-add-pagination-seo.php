<?php
class Migrations_Migration449 Extends Shopware\Framework\Migration\AbstractMigration
{
    public function up($modus)
    {
        $sql = <<<'EOD'
        SET @parent = (SELECT id FROM s_core_config_forms WHERE name = 'Frontend100' LIMIT 1);
EOD;
        $this->addSql($sql);

        $sql = <<<'EOD'
            INSERT IGNORE INTO `s_core_config_elements` (`form_id`, `name`, `value`, `label`, `description`, `type`, `required`, `position`, `scope`, `filters`, `validators`, `options`) VALUES
            (@parent, 'seoIndexPaginationLinks', 'b:0;', 'Paginierten Inhalt indexieren', 'Wenn aktiv, werden paginierte Listen (z.B. Suchergebnisse) für Such Engines indexiert', 'checkbox', 0, 0, 0, NULL, NULL, 'a:0:{}');
EOD;
        $this->addSql($sql);

        $sql = <<<'EOD'
        SET @elementId = (SELECT id FROM `s_core_config_elements` WHERE `name` = 'seoIndexPaginationLinks' LIMIT 1);
EOD;
        $this->addSql($sql);

        $sql = <<<'EOD'
        INSERT IGNORE INTO `s_core_config_element_translations` (`element_id`, `locale_id`, `label`, `description`)
        VALUES (@elementId, '2', 'Index paginated content', 'If set to true, paginated lists (eg. search results) will be made indexable by search engines');
EOD;
        $this->addSql($sql);
    }
}



