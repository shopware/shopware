<?php
class Migrations_Migration350 Extends Shopware\Framework\Migration\AbstractMigration
{
    public function up($modus)
    {
        $sql = <<<'EOD'
            ALTER TABLE s_core_templates ADD  parent_id INT NULL DEFAULT NULL ;
EOD;
        $this->addSql($sql);
    }
}



