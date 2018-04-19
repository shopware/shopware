<?php
class Migrations_Migration365 Extends Shopware\Framework\Migration\AbstractMigration
{
    public function up($modus)
    {
        $this->addSql("UPDATE s_core_plugins SET version = '2.0.0' WHERE name = 'Google';");

        $statement = $this->getConnection()->prepare("SELECT id FROM s_core_plugins WHERE name = 'Google' AND active = 1");
        $statement->execute();
        $data = $statement->fetchAll();

        if (!empty($data)) {
            $sql = <<<'EOD'
        SET @formId = (SELECT id FROM s_core_config_forms WHERE name = 'Google' LIMIT 1);

        INSERT IGNORE INTO `s_core_config_elements` (`form_id`, `name`, `value`, `label`, `description`, `type`, `required`, `position`, `scope`, `filters`, `validators`) VALUES
            (@formId, 'trackingLib', 's:2:"ga";', 'Tracking Bibliothek', 'Welche Tracking Bibliothek soll benutzt werden? Standardmäßig wird die veraltete Google Analytics verwendet. Der Wechsel zur Universal-Analytics-Bibliothek erfordert, das Sie Ihre Google Analytics Einstellungen aktualisieren. Für mehr Informationen besuchen Sie die offizielle Google-Dokumentation.', 'combo', 0, 0, 1, NULL, NULL);

        SET @elementId = (SELECT id FROM s_core_config_elements WHERE name = 'useShortDescriptionInListing' LIMIT 1);

        INSERT INTO `s_core_config_element_translations` (`element_id`, `locale_id`, `label`, `description`)
            VALUES (@elementId, 2, 'Tracking library', 'Tracking library to use. Defaults to legacy Google Analytics. Switching to Universal Analytics requires that you update you settings in your Google Analytics Admin page. Please check Google''s official documentation for more info.');
EOD;
            $this->addSql($sql);
        }
    }
}



