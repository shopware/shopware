<?php
class Migrations_Migration398 Extends Shopware\Framework\Migration\AbstractMigration
{
    public function up($modus)
    {

        // seo router config form
        $sql = <<<'EOD'
        SET @parent = (SELECT id FROM s_core_config_forms WHERE name = 'Frontend100' LIMIT 1);
EOD;
        $this->addSql($sql);

        // insert custom site router template config
        $sql = <<<'EOD'
        INSERT IGNORE INTO `s_core_config_elements` (`id`, `form_id`, `name`, `value`, `label`, `description`, `type`, `required`, `position`, `scope`, `filters`, `validators`, `options`) VALUES
        (NULL, @parent, 'seoCustomSiteRouteTemplate', 's:19:"{$site.description}";', 'SEO-Urls Shopseiten Template', NULL, 'text', 0, 0, 1, NULL, NULL, 'a:0:{}');
EOD;
        $this->addSql($sql);

        // insert form router template config
        $sql = <<<'EOD'
        INSERT IGNORE INTO `s_core_config_elements` (`id`, `form_id`, `name`, `value`, `label`, `description`, `type`, `required`, `position`, `scope`, `filters`, `validators`, `options`) VALUES
        (NULL, @parent, 'seoFormRouteTemplate', 's:12:"{$form.name}";', 'SEO-Urls Formular Template', NULL, 'text', 0, 0, 1, NULL, NULL, 'a:0:{}');
EOD;
        $this->addSql($sql);

        // custom site router template config
        $sql = <<<'EOD'
        SET @elementId = (SELECT id FROM `s_core_config_elements` WHERE `name` = 'seoCustomSiteRouteTemplate' LIMIT 1);
EOD;
        $this->addSql($sql);

        // insert custom site translation
        $sql = <<<'EOD'
        INSERT IGNORE INTO `s_core_config_element_translations` (`element_id`, `locale_id`, `label`)
        VALUES (@elementId, '2', 'Custom site SEO URLs template');
EOD;
        $this->addSql($sql);

        // form router template config
        $sql = <<<'EOD'
        SET @elementId = (SELECT id FROM `s_core_config_elements` WHERE `name` = 'seoFormRouteTemplate' LIMIT 1);
EOD;
        $this->addSql($sql);

        // insert form translation
        $sql = <<<'EOD'
        INSERT IGNORE INTO `s_core_config_element_translations` (`element_id`, `locale_id`, `label`)
        VALUES (@elementId, '2', 'Form SEO URLs template');
EOD;
        $this->addSql($sql);
    }
}



