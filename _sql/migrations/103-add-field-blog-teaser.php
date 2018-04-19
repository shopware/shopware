<?php
class Migrations_Migration103 Extends Shopware\Framework\Migration\AbstractMigration
{
    public function up($modus)
    {
        $sql = <<<'EOD'
SET @parent = (SELECT id FROM `s_library_component` WHERE `x_type`='emotion-components-category-teaser');
INSERT INTO  `s_library_component_field` (`id`, `componentID`, `name`, `x_type`, `value_type`, `field_label`, `support_text`, `help_title`, `help_text` ,`store`, `display_field`, `value_field`, `default_value`, `allow_blank`)
VALUES (NULL ,  @parent,  'blog_category',  'checkboxfield', '',  'Blog-Kategorie',  'Bei der ausgewählten Kategorie handelt es sich um eine Blog-Kategorie',  '',  '',  '',  '',  '',  '',  0);
EOD;

        $this->addSql($sql);
    }
}
