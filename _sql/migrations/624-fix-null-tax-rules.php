<?php
class Migrations_Migration624 extends Shopware\Framework\Migration\AbstractMigration
{
    public function up($modus)
    {
        $stateSql = <<<SQL
UPDATE s_core_tax_rules tax
INNER JOIN s_core_countries_states state ON state.id = tax.stateID
INNER JOIN s_core_countries country ON country.id = state.countryID
INNER JOIN s_core_countries_areas area ON area.id = country.areaID
SET tax.areaID=area.id, tax.countryID=country.id
WHERE tax.stateID IS NOT NULL;
SQL;

        $this->addSql($stateSql);

        $countrySql = <<<SQL
UPDATE s_core_tax_rules tax
INNER JOIN s_core_countries country ON country.id = tax.countryID
INNER JOIN s_core_countries_areas area ON area.id = country.areaID
SET tax.areaID=area.id
WHERE tax.countryID IS NOT NULL AND tax.stateID IS NULL;
SQL;

        $this->addSql($countrySql);
    }
}
