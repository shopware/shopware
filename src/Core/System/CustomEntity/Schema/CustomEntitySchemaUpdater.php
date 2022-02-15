<?php declare(strict_types=1);

namespace Shopware\Core\System\CustomEntity\Schema;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Doctrine\DBAL\Schema\Comparator;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Types;
use Shopware\Core\Framework\HttpException;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Serializer\NameConverter\CamelCaseToSnakeCaseNameConverter;

/**
 * @internal
 */
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
                    throw new HttpException('custom_entity_schema_updater.invalid_table_name', \sprintf('Table %s has to be prefixed with custom_', $table['name']));
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
            // Id columns do not need to be defined in the .xml, we do this automatically
            $table->addColumn('id', Types::BINARY, ['length' => 16, 'fixed' => true]);
            $table->setPrimaryKey(['id']);
        }

        // important: we add a `comment` to the table. This allows us to identify the custom entity modifications when run the cleanup
        $table->setComment(self::COMMENT);

        // we have to add only fields, which are not marked as translated
        $filtered = array_filter($fields, function (array $field) {
            return ($field['translatable'] ?? false) === false;
        });

        $this->addColumns($schema, $table, $filtered);

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

        $onDelete = [
            'set-null' => ['onUpdate' => 'cascade', 'onDelete' => 'set null'],
            'cascade' => ['onUpdate' => 'cascade', 'onDelete' => 'cascade'],
            'restrict' => ['onUpdate' => 'cascade', 'onDelete' => 'restrict'],
        ];

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
                case 'date':
                    $table->addColumn($field['name'], Types::DATETIME_MUTABLE, $nullable);

                    break;
                case 'json':
                case 'price':
                    $table->addColumn($field['name'], Types::JSON, $nullable);

                    break;
                case 'many-to-many':
                    // get reference name for foreign key building
                    $referenceName = $field['reference'];

                    // build mapping table name: `custom_entity_blog_products`
                    $mappingName = implode('_', [$name, $field['name']]);

                    // already defined?
                    if ($schema->hasTable($mappingName)) {
                        continue 2;
                    }

                    $mapping = $schema->createTable($mappingName);

                    // important: we add a `comment` to the table. This allows us to identify the custom entity modifications when run the cleanup
                    $mapping->setComment(self::COMMENT);

                    // add source id column: `custom_entity_blog_id`
                    $mapping->addColumn(self::id($name), Types::BINARY, $binary);

                    // add reference id column: `product_id`
                    $mapping->addColumn(self::id($referenceName), Types::BINARY, $binary);

                    // get reference table for versioning checks
                    $reference = $this->createTable($schema, $field['reference']);

                    $this->addInheritanceColumn($schema, $name, $field);

                    if (!$reference->hasColumn('version_id')) {
                        // version aware table needs a compound primary key (id, version_id)
                        $mapping->setPrimaryKey([self::id($name), self::id($referenceName)]);

                        // add foreign key to source table (custom_entity_blog.id <=> custom_entity_blog_products.custom_entity_blog_id), add cascade delete for both
                        $mapping->addForeignKeyConstraint($table, [self::id($name)], ['id'], $onDelete['cascade']);

                        // add foreign key to reference table (product.id <=> custom_entity_blog_products.product_id), add cascade delete for both
                        $mapping->addForeignKeyConstraint($reference, [self::id($referenceName)], ['id'], $onDelete['cascade']);

                        break;
                    }

                    $mapping->addColumn($referenceName . '_version_id', Types::BINARY, $binary);

                    //primary key is build with source_id, reference_id, reference_version_id
                    $mapping->setPrimaryKey([self::id($name), self::id($referenceName), $referenceName . '_version_id']);

                    // add foreign key to source table (custom_entity_blog.id <=> custom_entity_blog_products.custom_entity_blog_id), add cascade delete for both
                    $mapping->addForeignKeyConstraint($table, [self::id($name)], ['id'], $onDelete['cascade']);

                    // add foreign key to reference table (product.id <=> custom_entity_blog_products.product_id), add cascade delete for both
                    $mapping->addForeignKeyConstraint($reference, [self::id($referenceName), $referenceName . '_version_id'], ['id', 'version_id'], $onDelete['cascade']);

                    break;
                case 'many-to-one':
                case 'one-to-one':
                    if ($table->hasColumn(self::id($field['name']))) {
                        continue 2;
                    }
                    // first add foreign key column to custom entity table: `top_seller_id`
                    $table->addColumn(self::id($field['name']), Types::BINARY, $nullable + $binary);

                    // now check for on-delete foreign key configuration (cascade, restrict, set-null)
                    $options = $onDelete[$field['onDelete']];

                    // we need the reference table for version checks and foreign key constraint creation
                    $reference = $this->createTable($schema, $field['reference']);

                    // add inheritance column which matches the association name: `product.customEntityBlogTopSeller`
                    $this->addInheritanceColumn($schema, $name, $field);

                    // check for version support and consider version id in foreign key
                    if ($reference->hasColumn('version_id')) {
                        $table->addColumn($field['name'] . '_version_id', Types::BINARY, $nullable + $binary);
                        $table->addForeignKeyConstraint($reference, [self::id($field['name']), $field['name'] . '_version_id'], ['id', 'version_id'], $options);

                        break;
                    }

                    // add foreign key to reference table
                    $table->addForeignKeyConstraint($reference, [self::id($field['name'])], ['id'], $options);

                    break;

                case 'one-to-many':
                    // for one-to-many association, we don't need to add some columns in the custom entity table
                    $reference = $this->createTable($schema, $field['reference']);

                    $foreignKey = $table->getName() . '_' . self::id($field['name']);
                    if ($reference->hasColumn($foreignKey)) {
                        continue 2;
                    }

                    // now check for on-delete foreign key configuration (cascade, restrict, set-null)
                    $options = $onDelete[$field['onDelete']];

                    // important: we add a `comment` to the column. This allows us to identify the custom entity modification in sw-core tables when run the cleanup
                    $reference->addColumn($foreignKey, Types::BINARY, $nullable + $binary + ['comment' => self::COMMENT]);

                    // build foreign key with special naming. This allows us to identify the custom entity modification in sw-core tables when run the cleanup
                    $fk = substr('fk_ce_' . $reference->getName() . '_' . $foreignKey, 0, 64);
                    $reference->addForeignKeyConstraint($table, [$foreignKey], ['id'], $options, $fk);

                    // add inheritance column which matches the association name: `product.customEntityBlogTopSeller`
                    $this->addInheritanceColumn($schema, $name, $field);

                    break;
            }
        }
    }

    private function addInheritanceColumn(Schema $schema, string $entity, array $field): void
    {
        $reference = $this->createTable($schema, $field['reference']);

        if (!$reference->hasColumn('version_id')) {
            return;
        }

        $inherited = $field['inherited'] ?? false;
        if ($inherited === false) {
            return;
        }

        $name = self::kebabCaseToCamelCase($entity . '_' . $field['name']);

        $reference->addColumn($name, Types::BINARY, ['notnull' => false, 'default' => null, 'length' => 16, 'fixed' => true, 'comment' => self::COMMENT]);
    }

    private static function kebabCaseToCamelCase(string $string): string
    {
        return (new CamelCaseToSnakeCaseNameConverter())->denormalize(str_replace('-', '_', $string));
    }

    private static function id(string $name): string
    {
        return $name . '_id';
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
            throw new HttpException('custom_entity_schema_updater.schema_manager_not_found', 'The schema manager could not be found.');
        }

        return $manager;
    }

    private function getPlatform(): AbstractPlatform
    {
        $platform = $this->connection->getDatabasePlatform();
        if (!$platform instanceof AbstractPlatform) {
            throw new HttpException('custom_entity_schema_updater.database_platform_not_found', 'Database platform can not be detected');
        }

        return $platform;
    }
}
