<?php

class Migrations_Migration906 extends Shopware\Framework\Migration\AbstractMigration
{
    public function up($modus)
    {
        $sql = <<<'EOD'
SET @formId = (SELECT id FROM `s_core_config_forms` WHERE name='Frontend33');
EOD;

        $this->addSql($sql);

        $sql = <<<'EOD'
SET @configElementId = (SELECT id FROM `s_core_config_elements` WHERE name='countryshipping' AND form_id=@formId);
EOD;

        $this->addSql($sql);

        $sql = <<<'EOD'
DELETE FROM `s_core_config_elements` WHERE id=@configElementId
EOD;

        $this->addSql($sql);

        $sql = <<<'EOD'
DELETE FROM `s_core_config_values` WHERE element_id=@configElementId
EOD;

        $this->addSql($sql);
    }
}
