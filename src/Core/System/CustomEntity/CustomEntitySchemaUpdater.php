<?php declare(strict_types=1);

namespace Shopware\Core\System\CustomEntity;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Doctrine\DBAL\Schema\Comparator;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Types;

class CustomEntitySchemaUpdater
{
    private const COMMENT = 'custom-entity-element';

    private Connection $connection;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    public function update(): void
    {
        $tables = $this->connection->fetchAllAssociative('SELECT name, fields FROM custom_entity');

        $schema = $this->getSchemaManager()->createSchema();

        foreach ($schema->getTables() as $table) {
            if ($table->getComment() === self::COMMENT) {
                error_log(print_r($table->getName(), true) . "\n", 3, '/var/www/html/sw6/var/log/test.log');
                $schema->dropTable($table->getName());

                continue;
            }

            foreach ($table->getColumns() as $column) {
                if ($column->getComment() === self::COMMENT) {
                    $table->dropColumn($column->getName());
                }
            }
        }

        foreach ($tables as $table) {
            $fields = json_decode($table['fields'], true, 512, \JSON_THROW_ON_ERROR);

            if (strpos($table['name'], 'custom_') !== 0) {
                throw new \RuntimeException(sprintf('Table %s has to be prefixed with custom_', $table['name']));
            }

            $this->defineTable($schema, $table['name'], $fields);
        }

        $this->updateSchema($schema);
    }

    private function updateSchema(Schema $to): void
    {
        $from = $this->getSchemaManager()->createSchema();

        $diff = (new Comparator())
            ->compare($from, $to);

        $queries = $diff->toSql($this->getPlatform());

        foreach ($queries as $query) {
            $this->connection->executeStatement($query);
        }
    }

    private function defineTable(Schema $schema, string $name, array $fields): void
    {
        $table = $schema->hasTable($name)
            ? $schema->getTable($name)
            : $schema->createTable($name);

        if (!$table->hasColumn('id')) {
            $table->addColumn('id', Types::BINARY, ['length' => 16, 'fixed' => true]);
            $table->setPrimaryKey(['id']);
        }
        $table->setComment(self::COMMENT);

        $cascades = ['onUpdate' => 'cascade', 'onDelete' => 'cascade'];
        $nulls = ['onUpdate' => 'cascade', 'onDelete' => 'SET NULL'];

        foreach ($fields as $field) {
            if ($table->hasColumn($field['name'])) {
                continue;
            }

            switch ($field['type']) {
                case 'int':
                    $table->addColumn($field['name'], Types::INTEGER, ['unsinged' => true]);

                    break;
                case 'bool':
                    $table->addColumn($field['name'], Types::BOOLEAN);

                    break;
                case 'float':
                    $table->addColumn($field['name'], Types::FLOAT);

                    break;
                case 'string':
                case 'email':
                    $table->addColumn($field['name'], Types::STRING);

                    break;
                case 'text':
                    $table->addColumn($field['name'], Types::TEXT);

                    break;
                case 'json':
                    $table->addColumn($field['name'], Types::JSON);

                    break;
                case 'many-to-many':
                    $reference = $field['reference'];

                    if ($schema->hasTable($name . '_' . $reference)) {
                        continue 2;
                    }

                    $mapping = $schema->createTable($name . '_' . $reference);
                    $mapping->setComment(self::COMMENT);

                    $mapping->addColumn($name . '_id', Types::BINARY, ['length' => 16, 'fixed' => true]);
                    $mapping->addColumn($reference . '_id', Types::BINARY, ['length' => 16, 'fixed' => true]);
                    $mapping->setPrimaryKey([$name . '_id', $reference . '_id']);

                    $mapping->addForeignKeyConstraint($table, [$name . '_id'], ['id'], $cascades);
                    $mapping->addForeignKeyConstraint($schema->getTable($reference), [$reference . '_id'], ['id'], $cascades);

                    break;
                case 'many-to-one':
                case 'one-to-one':
                    if ($table->hasColumn($field['name'] . '_id')) {
                        continue 2;
                    }

                    $table->addColumn($field['name'] . '_id', Types::BINARY, ['length' => 16, 'fixed' => true, 'notnull' => false]);
                    $table->addForeignKeyConstraint($schema->getTable($field['reference']), [$field['name'] . '_id'], ['id'], $nulls);

                    break;
                case 'one-to-many':
                    $reference = $schema->hasTable($field['reference'])
                        ? $schema->getTable($field['reference'])
                        : $schema->createTable($field['reference']);

                    if (!$reference->hasColumn($name . '_id')) {
                        $reference->addColumn($name . '_id', Types::BINARY, ['length' => 16, 'fixed' => true, 'notnull' => false, 'comment' => self::COMMENT]);
                    }

                    break;
            }
        }
    }

    private function getSchemaManager(): AbstractSchemaManager
    {
        $manager = $this->connection->getSchemaManager();
        if (!$manager instanceof AbstractSchemaManager) {
            throw new \RuntimeException('Schema manager can not be created');
        }

        return $manager;
    }

    private function getPlatform(): AbstractPlatform
    {
        $platform = $this->connection->getDatabasePlatform();
        if (!$platform instanceof AbstractPlatform) {
            throw new \RuntimeException('Database platform can not be detected');
        }

        return $platform;
    }
}
