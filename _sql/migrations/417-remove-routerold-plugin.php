<?php
class Migrations_Migration417 Extends Shopware\Framework\Migration\AbstractMigration
{
    public function up($modus)
    {
        $sql = <<<SQL
DELETE
FROM s_core_plugins
WHERE name LIKE "RouterOld" AND author LIKE "shopware AG"
SQL;
        $this->addSql($sql);

$sql = <<<SQL
DELETE FROM
s_core_subscribes
WHERE listener LIKE 'Shopware_Plugins_Frontend_RouterOld_Bootstrap::%'
SQL;
        $this->addSql($sql);
    }
}
