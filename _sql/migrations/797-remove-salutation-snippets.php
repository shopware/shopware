<?php

class Migrations_Migration797 extends Shopware\Framework\Migration\AbstractMigration
{
    public function up($modus)
    {
        $statement = $this->connection->query("SELECT id, locale_id FROM s_core_shops WHERE `default` = 1 LIMIT 1");
        $shop = $statement->fetchAll(PDO::FETCH_ASSOC);
        $shop = array_shift($shop);

        $exists = $this->connection->query("SELECT id FROM s_core_snippets WHERE namespace = 'frontend/salutation' AND localeID = " . (int) $shop['locale_id'] . " AND shopID = " . (int) $shop['id'])->fetch(PDO::FETCH_COLUMN);
        if (!$exists) {
            $this->addSql(
                "UPDATE s_core_snippets SET localeID = " . (int) $shop['locale_id'] . " WHERE namespace = 'frontend/salutation' AND shopID = " . (int) $shop['id'] . " AND localeID = " . (int) $shop['id']
            );
        }

        $sql = "DELETE FROM s_core_snippets WHERE dirty = 0 AND namespace = 'frontend/salutation' AND value = '' AND shopID != " . (int) $shop['id'];
        $this->addSql($sql);

        $sql = "DELETE FROM s_core_snippets WHERE dirty = 0 AND namespace = 'frontend/salutation' AND value = '' AND shopID = " . (int) $shop['id'] . " AND localeID != " . (int) $shop['locale_id'];
        $this->addSql($sql);
    }
}
