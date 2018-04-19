<?php
class Migrations_Migration230 Extends Shopware\Framework\Migration\AbstractMigration
{
    public function up($modus)
    {
        $sql = <<<'EOD'
SET @parent = (SELECT id FROM `s_library_component` WHERE `x_type`='emotion-components-category-teaser' AND template = 'component_category_teaser' AND pluginID IS NULL LIMIT 1);
UPDATE s_library_component_field SET allow_blank = '1' WHERE name = 'image' AND componentID = @parent;

EOD;
        $this->addSql($sql);
    }
}
