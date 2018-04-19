<?php

use Shopware\Framework\Migration\AbstractMigration;

class Migrations_Migration913 extends AbstractMigration
{
    /**
     * @inheritdoc
     */
    public function up($modus)
    {
        $sql = <<<'SQL'
UPDATE `s_library_component_field` as comp_field
INNER JOIN s_library_component AS comp ON comp_field.componentID = comp.id
SET `comp_field`.`allow_blank` = 0 
WHERE `comp_field`.`name` = 'category_selection'
  AND `comp`.`x_type` = 'emotion-components-category-teaser'
SQL;
        $this->addSql($sql);
    }
}
