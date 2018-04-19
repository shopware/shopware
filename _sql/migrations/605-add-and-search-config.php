<?php

class Migrations_Migration605 Extends Shopware\Framework\Migration\AbstractMigration
{
    public function up($modus)
    {
        $this->insertConfigElements();
        $this->insertConfigElementTranslations();
    }

    /**
     * inserts the config elements
     */
    private function insertConfigElements()
    {
        $this->addSql("SET @formId = (SELECT id FROM `s_core_config_forms` WHERE `name` = 'Search' LIMIT 1);");
        $sql = <<<SQL
INSERT IGNORE INTO `s_core_config_elements` (`id`, `form_id`, `name`, `value`, `label`, `description`, `type`, `required`, `position`, `scope`, `filters`, `validators`)
VALUES (NULL, @formId, 'enableAndSearchLogic', 'b:0;', '"Und" Suchlogik verwenden', 'Die Suche zeigt nur Treffer an, in denen alle Suchbegriffe vorkommen.', 'checkbox', '0', '0', '1', NULL, NULL);
SQL;
        $this->addSql($sql);
    }

    /**
     * inserts the config element translations
     */
    private function insertConfigElementTranslations()
    {
        $this->addSql("SET @elementId = (SELECT id FROM `s_core_config_elements` WHERE `name` = 'enableAndSearchLogic' LIMIT 1);");
        $sql = <<<SQL
INSERT IGNORE INTO `s_core_config_element_translations` (`id`, `element_id`, `locale_id`, `label`, `description`)
VALUES (NULL, @elementId, '2', 'Use "and" search logic', 'The search will only return results that match all the search terms.');
SQL;
        $this->addSql($sql);
    }
}
