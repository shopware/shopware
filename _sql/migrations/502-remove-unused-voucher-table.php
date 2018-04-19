<?php
class Migrations_Migration502 Extends Shopware\Framework\Migration\AbstractMigration
{
    public function up($modus)
    {
        $sql = <<<'SQL'
DROP TABLE s_emarketing_vouchers_cashed;
SQL;

        $this->addSql($sql);
    }
}
