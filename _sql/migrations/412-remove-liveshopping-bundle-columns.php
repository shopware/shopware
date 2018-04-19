<?php
class Migrations_Migration412 Extends Shopware\Framework\Migration\AbstractMigration
{
    public function up($modus)
    {
        $sql = <<<SQL
ALTER TABLE s_order_basket
DROP bundle_join_ordernumber,
DROP liveshoppingID,
DROP bundleID
SQL;
        $this->addSql($sql);

        $sql = <<<SQL
ALTER TABLE s_emarketing_banners
DROP liveshoppingID
SQL;
        $this->addSql($sql);
    }
}
