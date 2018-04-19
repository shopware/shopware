<?php
class Migrations_Migration413 Extends Shopware\Framework\Migration\AbstractMigration
{
    public function up($modus)
    {
        $sql = <<<SQL
ALTER TABLE s_core_sessions
DROP expireref,
DROP created;
SQL;
        $this->addSql($sql);

        $sql = <<<SQL
ALTER TABLE s_core_sessions_backend
DROP created
SQL;
        $this->addSql($sql);
    }
}


