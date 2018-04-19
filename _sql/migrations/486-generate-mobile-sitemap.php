<?php
class Migrations_Migration486 Extends Shopware\Framework\Migration\AbstractMigration
{
    public function up($modus)
    {
        $this->createSitemapForm();
        $this->createMobileSitemapElement();
    }

    private function createSitemapForm()
    {
        $sql = <<<'EOD'
SET @parentForm = (SELECT id FROM `s_core_config_forms` WHERE `name` = 'Frontend' LIMIT 1);
EOD;
        $this->addSql($sql);

        $sql = <<<'EOD'
INSERT IGNORE INTO `s_core_config_forms` (`parent_id`, `name`, `label`, `description`, `position`, `scope`, `plugin_id`) VALUES
(@parentForm , 'Sitemap', 'Sitemap', NULL, 0, 0, NULL);
EOD;
        $this->addSql($sql);

        $sql = <<<'EOD'
SET @sitemapId = (SELECT id FROM `s_core_config_forms` WHERE `name` = 'Sitemap' LIMIT 1);
EOD;
        $this->addSql($sql);

        $sql = <<<'EOD'
INSERT IGNORE INTO `s_core_config_form_translations` (`form_id`, `locale_id`, `label`, `description`)
VALUES (@sitemapId, '2', 'Sitemap', NULL);
EOD;
        $this->addSql($sql);
    }

    private function createMobileSitemapElement()
    {
        $sql = <<<'EOD'
INSERT IGNORE INTO `s_core_config_elements`
(`form_id`, `name`, `value`, `label`, `description`, `type`, `required`, `position`, `scope`, `filters`, `validators`, `options`)
VALUES (@sitemapId, 'mobileSitemap', 'b:1;', 'Mobile Sitemap generieren', 'Wenn diese Option aktiviert ist, wird eine zusätzliche sitemap.xml mit der Struktur für mobile Endgeräte generiert.', 'boolean', '1', '1', '0', NULL, NULL, NULL);
EOD;
        $this->addSql($sql);

        $sql = <<<'EOD'
SET @elementId = (SELECT id FROM `s_core_config_elements` WHERE `name` = 'mobileSitemap' LIMIT 1);
EOD;
        $this->addSql($sql);

        $sql = <<<'EOD'
INSERT IGNORE INTO s_core_config_element_translations
(element_id, locale_id, label, description)
VALUES (@elementID, 2, 'Generate mobile sitemap', 'If enabled, an additional sitemap.xml file will be generated with the site structure for mobile devices');
EOD;
        $this->addSql($sql);
    }
}
