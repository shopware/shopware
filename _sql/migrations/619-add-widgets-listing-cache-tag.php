<?php

class Migrations_Migration619 extends Shopware\Framework\Migration\AbstractMigration
{
    public function up($modus)
    {
        $sql = <<<'EOD'
UPDATE `s_core_config_elements` SET `value` = 's:188:"frontend/listing price
frontend/index price
frontend/detail price
widgets/lastArticles detail
widgets/checkout checkout
widgets/compare compare
widgets/emotion price
widgets/listing price
";'
WHERE `s_core_config_elements`.`name` = 'noCacheControllers';
EOD;

        $this->addSql($sql);
    }
}
