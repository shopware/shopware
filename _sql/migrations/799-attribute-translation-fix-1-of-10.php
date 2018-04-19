<?php

class Migrations_Migration799 extends Shopware\Framework\Migration\AbstractMigration
{
    public function up($modus)
    {
        $this->prepare();

        require_once __DIR__ . '/common/AttributeTranslationMigrationHelper.php';
        $helper = new AttributeTranslationMigrationHelper($this->connection);
        $helper->migrate(200000);
    }

    private function prepare()
    {
        $this->connection->exec(<<<EOL
DROP TABLE IF EXISTS translation_migration_id;

CREATE TABLE `translation_migration_id` (
  `max_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
EOL
        );

        $this->connection->exec('INSERT INTO translation_migration_id (max_id) SELECT MAX(id) as id FROM s_core_translations;');
    }
}
