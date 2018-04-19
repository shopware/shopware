<?php
class Migrations_Migration469 Extends Shopware\Framework\Migration\AbstractMigration
{
    public function up($modus)
    {
        $this->addSql("SET @formId = (SELECT `id` FROM `s_core_config_forms` WHERE name = 'Frontend100');");
        $sql = <<<EOD
          INSERT IGNORE INTO `s_core_config_elements` (`form_id`, `name`, `value`, `label`, `description`, `type`, `required`, `position`, `scope`, `filters`, `validators`, `options`)
          VALUES (@formId, 'RelatedArticlesOnArticleNotFound', 'b:1;', 'Zeige ähnliche Artikel auf der \"Artikel nicht gefunden\" Seite an', 'Wenn aktiviert, zeigt die \"Artikel nicht gefunden\" Seite die ähnlichen Artikel Vorschläge an. Deaktivieren Sie diese Einstellung um die Standard \"Seite nicht gefunden\" Seite darzustellen.', 'boolean', 1, 0, 1, NULL, NULL, '');
          ");
EOD;
        $this->addSql($sql);
        $this->addSql("SET @elementId = (SELECT id FROM s_core_config_elements WHERE name = 'RelatedArticlesOnArticleNotFound' LIMIT 1);");

        $this->addSql("
            INSERT IGNORE INTO `s_core_config_element_translations` (`element_id`, `locale_id`, `label`, `description`)
            VALUES (@elementId, '2', 'Display related articles on \"Article not found\" page', 'If enabled, \"Article not found\" page will display related articles suggestions. Disable to use the standard \"Page not found\" page');
        ");
    }
}
