<?php

class Migrations_Migration793 extends Shopware\Framework\Migration\AbstractMigration
{
    public function up($modus)
    {
        $exists = $this->connection->query("SELECT id FROM s_search_tables WHERE `table` = 's_articles_attributes'");
        $exists = $exists->fetch(PDO::FETCH_COLUMN);

        if ((int) $exists > 0) {
            return;
        }

        $sql = <<<'EOD'
INSERT INTO `s_search_tables` (`id`, `table`, `referenz_table`, `foreign_key`, `where`) VALUES
(NULL, 's_articles_attributes', NULL, NULL, NULL);
EOD;
        $this->addSql($sql);
    }
}
