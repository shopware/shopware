<?php

class Migrations_Migration808 extends Shopware\Framework\Migration\AbstractMigration
{
    public function up($modus)
    {
        require_once __DIR__ . '/common/AttributeTranslationMigrationHelper.php';
        $helper = new AttributeTranslationMigrationHelper($this->connection);
        $helper->migrate(200000);

        $this->connection->exec('DROP TABLE IF EXISTS translation_migration_id;');
    }
}
