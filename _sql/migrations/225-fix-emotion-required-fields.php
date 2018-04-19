<?php
class Migrations_Migration225 Extends Shopware\Framework\Migration\AbstractMigration
{
    public function up($modus)
    {
        $sql = <<<'EOD'
SET @parent = (SELECT id FROM `s_library_component` WHERE `x_type`='emotion-components-banner-slider' AND template = 'component_banner_slider' AND pluginID IS NULL LIMIT 1);
UPDATE s_library_component_field SET allow_blank = '1' WHERE name = 'banner_slider_title' AND componentID = @parent;

SET @parent = (SELECT id FROM `s_library_component` WHERE `x_type`='emotion-components-manufacturer-slider' AND template = 'component_manufacturer_slider' AND pluginID IS NULL LIMIT 1);
UPDATE s_library_component_field SET allow_blank = '1' WHERE name = 'manufacturer_slider_title' AND componentID = @parent;

SET @parent = (SELECT id FROM `s_library_component` WHERE `x_type`='emotion-components-html-element' AND template = 'component_html' AND pluginID IS NULL LIMIT 1);
UPDATE s_library_component_field SET allow_blank = '1' WHERE name = 'cms_title' AND componentID = @parent;

SET @parent = (SELECT id FROM `s_library_component` WHERE `x_type`='emotion-components-banner' AND template = 'component_banner' AND pluginID IS NULL LIMIT 1);
UPDATE s_library_component_field SET allow_blank = '1' WHERE name = 'link' AND componentID = @parent;

SET @parent = (SELECT id FROM `s_library_component` WHERE `x_type`='emotion-components-article-slider' AND template = 'component_article_slider' AND pluginID IS NULL LIMIT 1);
UPDATE s_library_component_field SET allow_blank = '1' WHERE name = 'article_slider_title' AND componentID = @parent;

EOD;
        $this->addSql($sql);
    }
}
