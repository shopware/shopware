<?php
class Migrations_Migration393 Extends Shopware\Framework\Migration\AbstractMigration
{
    public function up($modus)
    {
        $this->addSql("SET @formId = (SELECT `id` FROM `s_core_config_forms` WHERE name = 'Frontend100');");
        $sql = <<<EOD
          INSERT IGNORE INTO `s_core_config_elements` (`form_id`, `name`, `value`, `label`, `description`, `type`, `required`, `position`, `scope`, `filters`, `validators`, `options`)
          VALUES (@formId, 'PageNotFoundDestination', 'i:-2;', '\"Seite nicht gefunden\" Ziel', 'Wenn der Besucher eine nicht existierende Seite aufruft, wird ihm diese angezeigt.', 'select', 1, 0, 1, NULL, NULL, 'a:4:{s:5:"store";s:35:"base.PageNotFoundDestinationOptions";s:12:"displayField";s:4:"name";s:10:"valueField";s:2:"id";s:10:"allowBlank";s:5:"false";}');
          ");
EOD;
        $this->addSql($sql);
        $this->addSql("SET @elementId = (SELECT id FROM s_core_config_elements WHERE name = 'PageNotFoundDestination' LIMIT 1);");

        $this->addSql("
            INSERT IGNORE INTO `s_core_config_element_translations` (`element_id`, `locale_id`, `label`, `description`)
            VALUES (@elementId, '2', '\"Page not found\" destination', 'When the user requests a non-existent page, he will be shown the following page.' );
        ");

        $sql = <<<EOD
          INSERT IGNORE INTO `s_core_config_elements` (`form_id`, `name`, `value`, `label`, `description`, `type`, `required`, `position`, `scope`, `filters`, `validators`)
          VALUES (@formId, 'PageNotFoundCode', 'i:404;', '\"Seite nicht gefunden\" Fehlercode', 'Übertragener HTTP Statuscode bei \"Seite nicht gefunden\" meldungen', 'number', 1, 0, 1, NULL, NULL);
          ");
EOD;
        $this->addSql($sql);
        $this->addSql("SET @elementId = (SELECT id FROM s_core_config_elements WHERE name = 'PageNotFoundCode' LIMIT 1);");

        $this->addSql("
            INSERT IGNORE INTO `s_core_config_element_translations` (`element_id`, `locale_id`, `label`, `description`)
            VALUES (@elementId, '2', '\"Page not found\" error code', 'HTTP code used in \"Page not found\" responses' );
        ");
    }
}
