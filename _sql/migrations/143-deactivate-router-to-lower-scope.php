<?php
class Migrations_Migration143 Extends Shopware\Framework\Migration\AbstractMigration
{
    public function up($modus)
    {
        $sql = <<<'EOD'
            UPDATE  `s_core_config_elements` SET  `scope` = '0' WHERE  `s_core_config_elements`.`name` = 'routertolower';

            SET @elementId = (SELECT id FROM s_core_config_elements WHERE name = 'routertolower');

            SET @shopId = (SELECT id FROM s_core_shops WHERE `default` = 1);

            DELETE FROM s_core_config_values WHERE element_id = @elementId AND shop_id != @shopId;
EOD;

        $this->addSql($sql);
    }
}



