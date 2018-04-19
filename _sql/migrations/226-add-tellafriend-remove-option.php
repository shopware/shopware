<?php
class Migrations_Migration226 Extends Shopware\Framework\Migration\AbstractMigration
{
    public function up($modus)
    {
        $sql = <<<'EOD'
        SET @parent = (SELECT id FROM s_core_config_forms WHERE name = 'Recommendation' AND plugin_id IS NULL LIMIT 1);

        INSERT IGNORE INTO `s_core_config_elements`
            (`form_id`, `name`, `value`, `label`, `description`, `type`, `required`, `position`, `scope`, `filters`, `validators`, `options`)
        VALUES
            (@parent, 'showTellAFriend', 'b:0;', 'Artikel weiterempfehlen anzeigen', NULL, 'boolean', 0, 7, 1, NULL, NULL, NULL);

        SET @element = (SELECT id FROM s_core_config_elements WHERE name = 'showTellAFriend' LIMIT 1);

        INSERT IGNORE INTO `s_core_config_element_translations` (`id`, `element_id`, `locale_id`, `label`, `description`)
        VALUES
        (NULL, @element, '2', 'Show recommend product', NULL);
EOD;
        $this->addSql($sql);
    }
}
