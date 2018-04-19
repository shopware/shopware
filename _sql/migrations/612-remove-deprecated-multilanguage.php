<?php

class Migrations_Migration612 Extends Shopware\Framework\Migration\AbstractMigration
{
    public function up($modus)
    {
        $sql = <<<'EOD'
DROP TABLE s_core_multilanguage;
EOD;

        $this->addSql($sql);
    }
}
