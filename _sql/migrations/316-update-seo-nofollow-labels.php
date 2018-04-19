<?php
class Migrations_Migration316 extends Shopware\Framework\Migration\AbstractMigration
{
    public function up($modus)
    {
        $this->addSql("
            UPDATE `s_core_config_elements` SET `label` = 'SEO-Noindex Querys' WHERE `label` = 'SEO-Nofollow Querys' AND `name` = 'seoqueryblacklist';
            UPDATE `s_core_config_elements` SET `label` = 'SEO-Noindex Viewports' WHERE `label` = 'SEO-Nofollow Viewports' AND `name` = 'seoviewportblacklist';
            UPDATE `s_core_config_element_translations` SET `label` = 'SEO noindex queries' WHERE `label` = 'SEO nofollow queries' AND `locale_id` = 2;
            UPDATE `s_core_config_element_translations` SET `label` = 'SEO noindex viewsports' WHERE `label` = 'SEO nofollow viewports' AND `locale_id` = 2;
        ");
    }
}
