<?php
class Migrations_Migration200 Extends Shopware\Framework\Migration\AbstractMigration
{
    public function up($modus)
    {
        $sql = <<<'EOD'
DROP TABLE s_core_factory;
EOD;

        $this->addSql($sql);
    }
}
