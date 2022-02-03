<?php declare(strict_types=1);

namespace Shopware\Core\System\CustomEntity\Schema;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Doctrine\DBAL\Schema\Comparator;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Types;
use Symfony\Component\Lock\LockFactory;

class CustomEntitySchemaUpdater
{
    private const COMMENT = 'custom-entity-element';

    private Connection $connection;

    private LockFactory $lockFactory;

    public function __construct(Connection $connection, LockFactory $lockFactory)
    {
        $this->connection = $connection;
        $this->lockFactory = $lockFactory;
    }

    public function update(): void
    {
        $this->lock(function (): void {
            $tables = $this->connection->fetchAllAssociative('SELECT name, fields FROM custom_entity');

            $schema = $this->getSchemaManager()->createSchema();

            foreach ($schema->getTables() as $table) {
                if ($table->getComment() === self::COMMENT) {
                    $schema->dropTable($table->getName());

                    continue;
                }

                foreach ($table->getForeignKeys() as $foreignKey) {
                    if (\strpos($foreignKey->getName(), 'fk_ce_') === 0) {
                        $table->removeForeignKey($foreignKey->getName());
                    }
                }

                foreach ($table->getColumns() as $column) {
                    if ($column->getComment() === self::COMMENT) {
                        $table->dropColumn($column->getName());
                    }
                }
            }

            foreach ($tables as $table) {
                $fields = \json_decode($table['fields'], true, 512, \JSON_THROW_ON_ERROR);

                if (\strpos($table['name'], 'custom_entity_') !== 0) {
                    throw new \RuntimeException(\sprintf('Table %s has to be prefixed with custom_', $table['name']));
                }

                $this->defineTable($schema, $table['name'], $fields);
            }

            $this->updateSchema($schema);
        });
    }

    private function lock(\Closure $closure): void
    {
        $lock = $this->lockFactory->createLock('custom-entity::schema-update', 30);

        if ($lock->acquire(true)) {
            $closure();

            $lock->release();
        }
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
        $table = $this->createTable($schema, $name);

        if (!$table->hasColumn('id')) {
            $table->addColumn('id', Types::BINARY, ['length' => 16, 'fixed' => true]);
            $table->setPrimaryKey(['id']);
        }
        $table->setComment(self::COMMENT);

        $noneTranslated = array_filter($fields, function (array $field) {
            $translated = $field['translatable'] ?? false;

            return $translated === false;
        });

        $this->addColumns($schema, $table, $noneTranslated);

        $binary = ['length' => 16, 'fixed' => true];

        $translated = array_filter($fields, function (array $field) {
            return $field['translatable'] ?? false;
        });

        if (empty($translated)) {
            return;
        }

        $translation = $this->createTable($schema, $name . '_translation');
        $translation->setComment(self::COMMENT);
        $translation->addColumn($name . '_id', Types::BINARY, $binary);
        $translation->addColumn('language_id', Types::BINARY, $binary);

        $fk = 'fk_ce_' . $translation->getName() . '_root';
        $translation->addForeignKeyConstraint($table, [$name . '_id'], ['id'], ['onUpdate' => 'cascade', 'onDelete' => 'cascade'], $fk);

        $fk = 'fk_ce_' . $translation->getName() . '_language_id';
        $translation->addForeignKeyConstraint($table, [$name . '_id'], ['id'], ['onUpdate' => 'cascade', 'onDelete' => 'cascade'], $fk);

        $this->addColumns($schema, $translation, $translated);
    }

    private function addColumns(Schema $schema, Table $table, array $fields): void
    {
        $name = $table->getName();
        $binary = ['length' => 16, 'fixed' => true];
        $cascades = ['onUpdate' => 'cascade', 'onDelete' => 'cascade'];
        $nulls = ['onUpdate' => 'cascade', 'onDelete' => 'set null'];
        $restrict = ['onUpdate' => 'cascade', 'onDelete' => 'restrict'];

        if (!$table->hasColumn('created_at')) {
            $table->addColumn('created_at', Types::DATETIME_MUTABLE, ['notnull' => true]);
        }

        if (!$table->hasColumn('updated_at')) {
            $table->addColumn('updated_at', Types::DATETIME_MUTABLE, ['notnull' => false]);
        }

        foreach ($fields as $field) {
            if ($table->hasColumn($field['name'])) {
                continue;
            }

            $required = $field['required'] ?? false;

            $nullable = $required ? [] : ['notnull' => false, 'default' => null];

            switch ($field['type']) {
                case 'int':
                    $table->addColumn($field['name'], Types::INTEGER, $nullable + ['unsinged' => true]);

                    break;
                case 'bool':
                    $table->addColumn($field['name'], Types::BOOLEAN, $nullable);

                    break;
                case 'float':
                    $table->addColumn($field['name'], Types::FLOAT, $nullable);

                    break;
                case 'string':
                case 'email':
                    $table->addColumn($field['name'], Types::STRING, $nullable);

                    break;
                case 'text':
                    $table->addColumn($field['name'], Types::TEXT, $nullable);

                    break;
                case 'json':
                    $table->addColumn($field['name'], Types::JSON, $nullable);

                    break;
                case 'many-to-many':
                    $referenceName = $field['reference'];

                    $mappingName = [$name, $referenceName];
                    sort($mappingName);
                    $mappingName = implode('_', $mappingName);

                    if ($schema->hasTable($mappingName)) {
                        continue 2;
                    }

                    $reference = $this->createTable($schema, $field['reference']);

                    $mapping = $schema->createTable($mappingName);
                    $mapping->setComment(self::COMMENT);

                    $mapping->addColumn('created_at', Types::DATETIME_MUTABLE, ['notnull' => true]);
                    $mapping->addColumn('updated_at', Types::DATETIME_MUTABLE, ['notnull' => false]);

                    $mapping->addColumn($name . '_id', Types::BINARY, $binary);
                    $mapping->addColumn($referenceName . '_id', Types::BINARY, $binary);

                    if (!$reference->hasColumn('version_id')) {
                        $mapping->setPrimaryKey([$name . '_id', $referenceName . '_id']);
                        $mapping->addForeignKeyConstraint($table, [$name . '_id'], ['id'], $cascades);
                        $mapping->addForeignKeyConstraint($reference, [$referenceName . '_id'], ['id'], $cascades);

                        break;
                    }

                    $mapping->addColumn($referenceName . '_version_id', Types::BINARY, $binary);
                    $mapping->setPrimaryKey([$name . '_id', $referenceName . '_id', $referenceName . '_version_id']);
                    $mapping->addForeignKeyConstraint($table, [$name . '_id'], ['id'], $cascades);
                    $mapping->addForeignKeyConstraint($reference, [$referenceName . '_id', $referenceName . '_version_id'], ['id', 'version_id'], $cascades);

                    break;
                case 'many-to-one':
                case 'one-to-one':
                    if ($table->hasColumn($field['name'] . '_id')) {
                        continue 2;
                    }
                    $table->addColumn($field['name'] . '_id', Types::BINARY, $nullable + $binary);

                    $options = $required ? $restrict : $nulls;

                    $reference = $this->createTable($schema, $field['reference']);

                    if ($reference->hasColumn('version_id')) {
                        $table->addColumn($field['name'] . '_version_id', Types::BINARY, $nullable + $binary);
                        $table->addForeignKeyConstraint($reference, [$field['name'] . '_id', $field['name'] . '_version_id'], ['id', 'version_id'], $options);

                        break;
                    }

                    $table->addForeignKeyConstraint($reference, [$field['name'] . '_id'], ['id'], $options);

                    break;
                case 'one-to-many':
                    $reference = $this->createTable($schema, $field['reference']);

                    if ($reference->hasColumn($name . '_id')) {
                        continue 2;
                    }

                    $options = $nulls;
                    if (strpos($reference->getName(), 'custom_entity_') === 0) {
                        $nullable = [];
                        $options = $cascades;
                    }

                    $reference->addColumn($name . '_id', Types::BINARY, $nullable + $binary + ['comment' => self::COMMENT]);

                    $fk = substr('fk_ce_' . $reference->getName() . '_' . $name . '_id', 0, 64);
                    $reference->addForeignKeyConstraint($table, [$name . '_id'], ['id'], $options, $fk);

                    break;
            }
        }
    }

    private function createTable(Schema $schema, string $name): Table
    {
        return $schema->hasTable($name)
            ? $schema->getTable($name)
            : $schema->createTable($name);
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
