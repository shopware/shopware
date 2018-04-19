<?php

class Migrations_Migration736 extends Shopware\Framework\Migration\AbstractMigration
{
    /**
     * @param string $modus
     * @return void
     */
    public function up($modus)
    {
        $sql = <<<'EOD'
UPDATE `s_library_component_field` as `field`
SET `position` = 9
WHERE `field`.`name` = 'article'
AND `field`.`componentID` = 4
EOD;

        $this->addSql($sql);

        $sql = <<<'EOD'
INSERT INTO `s_library_component_field`
  (`componentID`, `name`, `x_type`, `field_label`, `allow_blank`, `translatable`, `position`)
  VALUES ('4', 'article_category', 'emotion-components-fields-category-selection', 'Kategorie', '1', '0', '9');
EOD;

        $this->addSql($sql);
    }
}
