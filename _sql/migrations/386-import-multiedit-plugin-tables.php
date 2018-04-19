<?php
class Migrations_Migration386 Extends Shopware\Framework\Migration\AbstractMigration
{
    public function up($modus)
    {
        // Check if the table from the plugin is available
        try {
            $statement = $this->connection->query("SHOW TABLES LIKE 's_plugin_multi_edit_filter'");
            $result = $statement->fetch(PDO::FETCH_COLUMN);
        } catch(Exception $e) {
            return;
        }

        // If not - return
        if (empty($result))  {
            return;
        }

        // Else: Truncate the new filter table and import the existing filters
        $this->addSql('TRUNCATE `s_multi_edit_filter`');

        $sql = <<<'EOD'
            INSERT INTO s_multi_edit_filter (`name`, `filter_string`, `description`, `created`, `is_favorite`, `is_simple`)
            SELECT `name`, `filter_string`, `description`, `created`, `is_favorite`, `is_simple` FROM s_plugin_multi_edit_filter;
EOD;

        $this->addSql($sql);
    }
}
