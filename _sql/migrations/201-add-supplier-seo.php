<?php
class Migrations_Migration201 Extends Shopware\Framework\Migration\AbstractMigration
{
    public function up($modus)
    {
        $sql = <<<'EOD'
        ALTER TABLE  `s_articles_supplier`
        ADD  `meta_title` VARCHAR( 255 ) NULL ,
        ADD  `meta_description` VARCHAR( 255 ) NULL ,
        ADD  `meta_keywords` VARCHAR( 255 ) NULL ;

        SET @parent = (SELECT id FROM s_core_config_forms WHERE name = 'Frontend100' LIMIT 1);

        INSERT IGNORE INTO `s_core_config_elements` (`id`, `form_id`, `name`, `value`, `label`, `description`, `type`, `required`, `position`, `scope`, `filters`, `validators`, `options`) VALUES
        (NULL, @parent, 'seoSupplier', 'b:1;', 'Hersteller SEO-Informationen anwenden', NULL, 'checkbox', 0, 0, 1, NULL, NULL, 'a:0:{}');

        SET @elementId = (SELECT id FROM `s_core_config_elements` WHERE `name` = 'seoSupplier' LIMIT 1);
        INSERT IGNORE INTO `s_core_config_element_translations` (`element_id`, `locale_id`, `label`)
        VALUES (@elementId, '2', 'Supplier SEO');
EOD;
        $this->addSql($sql);
    }
}



