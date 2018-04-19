<?php
class Migrations_Migration388 Extends Shopware\Framework\Migration\AbstractMigration
{
    public function up($modus)
    {
        $this->addSql("ALTER TABLE s_library_component_field ADD position INT NULL;");
        $this->addSql("UPDATE s_library_component_field SET position = id;");
        $this->addSql("SET @parent = (SELECT id FROM `s_library_component` WHERE `x_type`='emotion-components-article-slider' AND template = 'component_article_slider' AND pluginID IS NULL LIMIT 1);");
        $this->addSql("SET @maxNumberPosition = (SELECT id FROM `s_library_component_field` WHERE `name`='article_slider_max_number' AND componentID = @parent LIMIT 1);");
        $this->addSql("UPDATE s_library_component_field SET position = position+1 WHERE componentID = @parent AND id >= @maxNumberPosition;");
        $this->addSql("
            INSERT INTO `s_library_component_field` (`componentID`, `name`, `x_type`, `value_type`, `field_label`, `support_text`, `help_title`, `help_text`, `store`, `display_field`, `value_field`, `default_value`, `allow_blank`, `position`) VALUES
            (@parent, 'article_slider_category', 'emotion-components-fields-category-selection', '', '', '', '', '', '', '', '', '', 1, @maxNumberPosition);
EOD;
        ");
    }
}
