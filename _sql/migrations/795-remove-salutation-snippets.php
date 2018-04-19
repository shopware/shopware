<?php

class Migrations_Migration795 extends Shopware\Framework\Migration\AbstractMigration
{
    public function up($modus)
    {
        $statement = $this->connection->query("SELECT id, locale_id FROM s_core_shops WHERE `default` = 1 LIMIT 1");
        $shop = $statement->fetchAll(PDO::FETCH_ASSOC);
        $shop = array_shift($shop);
        $sql = "DELETE FROM s_core_snippets WHERE dirty = 0 AND namespace = 'frontend/salutation' AND value = '' AND (shopID != " . (int) $shop['id'] . " OR localeID != " . (int) $shop['locale_id'] . ")";
        $this->addSql($sql);
    }
}
