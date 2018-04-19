<?php
class Migrations_Migration223 Extends Shopware\Framework\Migration\AbstractMigration
{
    public function up($modus)
    {
        $sql = <<<'EOD'
        SET @parent = (SELECT id FROM s_core_config_forms WHERE name = 'Statistics' LIMIT 1);

        INSERT IGNORE INTO `s_core_config_elements` (`id`, `form_id`, `name`, `value`, `label`, `description`, `type`, `required`, `position`, `scope`, `filters`, `validators`, `options`) VALUES
        (NULL, @parent, 'maximumReferrerAge', 's:2:"90";', 'Maximales Alter für Referrer Statistikdaten', 'Alte Referrer Daten werden über den Aufräumen Cronjob gelöscht, falls aktiv', 'text', 0, 0, 1, NULL, NULL, 'a:0:{}');

        SET @elementId = (SELECT id FROM `s_core_config_elements` WHERE `name` = 'maximumReferrerAge' LIMIT 1);
        INSERT IGNORE INTO `s_core_config_element_translations` (`element_id`, `locale_id`, `label`, `description`)
        VALUES (@elementId, '2', 'Maximum age for referrer statistics', 'Old referrer data will be deleted by the cron job call if active' );


        INSERT IGNORE INTO `s_core_config_elements` (`id`, `form_id`, `name`, `value`, `label`, `description`, `type`, `required`, `position`, `scope`, `filters`, `validators`, `options`) VALUES
        (NULL, @parent, 'maximumImpressionAge', 's:2:"90";', 'Maximales Alter für Artikel-Impressions', 'Alte Impression Daten werden über den Aufräumen Cronjob gelöscht, falls aktiv', 'text', 0, 0, 1, NULL, NULL, 'a:0:{}');

        SET @elementId = (SELECT id FROM `s_core_config_elements` WHERE `name` = 'maximumImpressionAge' LIMIT 1);
        INSERT IGNORE INTO `s_core_config_element_translations` (`element_id`, `locale_id`, `label`, `description`)
        VALUES (@elementId, '2', 'Maximum age for impression statistics', 'Old impression data will be deleted by the cron job call if active' );
EOD;
        $this->addSql($sql);
    }
}
