<?php
class Migrations_Migration104 Extends Shopware\Framework\Migration\AbstractMigration
{
    public function up($modus)
    {
        $sql = <<<'EOD'
SET @parent = (SELECT id FROM `s_library_component` WHERE `x_type`='emotion-components-blog');
INSERT INTO  `s_library_component_field` (`id`, `componentID`, `name`, `x_type`, `value_type`, `field_label`, `support_text`, `help_title`, `help_text` ,`store`, `display_field`, `value_field`, `default_value`, `allow_blank`)
VALUES (NULL ,  @parent,  'thumbnail_size',  'textfield', '',  'Thumbnail-Größe',  'Thumbnail-Nummer, die verwendet werden soll. Im Standard stehen Ihnen 0 bis 3 zur Verfügung.',  '',  '',  '',  '',  '',  '2',  1);
EOD;

        $this->addSql($sql);
    }
}
