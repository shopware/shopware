<?php declare(strict_types=1);

namespace Shopware\Core\System\CustomEntity\Schema;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Types;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Serializer\NameConverter\CamelCaseToSnakeCaseNameConverter;

/**
 * @internal
 *
 * @phpstan-type CustomEntityField array{name: string, type: string, required?: bool, translatable?: bool, reference: string, inherited?: bool, onDelete: string, storeApiAware?: bool}
 */
#[Package('core')]
class SchemaUpdater
{
    final public const TABLE_PREFIX = 'custom_entity_';

    final public const SHORTHAND_TABLE_PREFIX = 'ce_';

    private const COMMENT = 'custom-entity-element';

    /**
     * @param list<array{name: string, fields: string}> $customEntities
     */
    public function applyCustomEntities(Schema $schema, array $customEntities): void
    {
        /** @var array<string, list<CustomEntityField>> $tables */
        $tables = [];

        foreach ($customEntities as $customEntity) {
            $entityName = $customEntity['name'];

            /** @var list<CustomEntityField> $fields */
            $fields = \json_decode($customEntity['fields'], true, 512, \JSON_THROW_ON_ERROR);

            if (!\str_starts_with($entityName, self::TABLE_PREFIX) && !\str_starts_with($entityName, self::SHORTHAND_TABLE_PREFIX)) {
                throw new \RuntimeException(
                    \sprintf('Table "%s" has to be prefixed with "%s or %s"', $entityName, self::TABLE_PREFIX, self::SHORTHAND_TABLE_PREFIX)
                );
            }

            $tables[$entityName] = $fields;
        }

        foreach ($tables as $name => $fields) {
            $this->defineTable($schema, $name, $fields);
        }

        // All primary keys must be defined before calling addAssociationFields
        foreach ($tables as $name => $fields) {
            $this->addAssociationFields($schema, $name, $fields);
        }
    }

    /**
     * @param CustomEntityField $field
     */
    private function isAssociation(array $field): bool
    {
        $associations = ['many-to-one', 'one-to-many', 'many-to-many', 'one-to-one'];

        return \in_array($field['type'], $associations, true);
    }

    /**
     * @param list<CustomEntityField> $fields
     */
    private function defineTable(Schema $schema, string $name, array $fields): void
    {
        $table = $this->createTable($schema, $name);

        // Id columns do not need to be defined in the .xml, we do this automatically
        $table->addColumn('id', Types::BINARY, ['length' => 16, 'fixed' => true]);
        $table->setPrimaryKey(['id']);

        // important: we add a `comment` to the table. This allows us to identify the custom entity modifications when run the cleanup
        $table->setComment(self::COMMENT);

        // we have to add only fields, which are not marked as translated
        $filtered = array_filter($fields, fn (array $field) => ($field['translatable'] ?? false) === false);

        $filtered = array_filter($filtered, fn (array $field) => !$this->isAssociation($field));

        $this->addColumns($schema, $table, $filtered);

        $binary = ['length' => 16, 'fixed' => true];

        $translated = array_filter($fields, fn (array $field) => $field['translatable'] ?? false);

        if (empty($translated)) {
            return;
        }
        $languageTable = $schema->getTable('language');

        $translation = $this->createTable($schema, $name . '_translation');
        $translation->setComment(self::COMMENT);
        $translation->addColumn($name . '_id', Types::BINARY, $binary);
        $translation->addColumn('language_id', Types::BINARY, $binary);
        $translation->setPrimaryKey([$name . '_id', 'language_id']);

        $fk = substr('fk_ce_' . $translation->getName() . '_root', 0, 64);
        $translation->addForeignKeyConstraint($table, [$name . '_id'], ['id'], ['onUpdate' => 'cascade', 'onDelete' => 'cascade'], $fk);

        $fk = substr('fk_ce_' . $translation->getName() . '_language_id', 0, 64);
        $translation->addForeignKeyConstraint($languageTable, ['language_id'], ['id'], ['onUpdate' => 'cascade', 'onDelete' => 'cascade'], $fk);

        $this->addColumns($schema, $translation, $translated);
    }

    /**
     * @param list<CustomEntityField> $fields
     */
    private function addAssociationFields(Schema $schema, string $name, array $fields): void
    {
        $table = $this->createTable($schema, $name);
        $filtered = array_filter($fields, fn (array $field) => $this->isAssociation($field));
        $this->addColumns($schema, $table, $filtered);
    }

    /**
     * @param list<CustomEntityField> $fields
     */
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
            $required = $field['required'] ?? false;

            $nullable = $required ? [] : ['notnull' => false, 'default' => null];

            switch ($field['type']) {
                case 'int':
                    $table->addColumn($field['name'], Types::INTEGER, $nullable + ['unsigned' => true]);

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
                        $fkName = substr('fk_ce_' . $mapping->getName() . '_' . $name, 0, 64);
                        $mapping->addForeignKeyConstraint($table, [self::id($name)], ['id'], $onDelete['cascade'], $fkName);

                        // add foreign key to reference table (product.id <=> custom_entity_blog_products.product_id), add cascade delete for both
                        $fkName = substr('fk_ce_' . $mapping->getName() . '_' . $referenceName, 0, 64);
                        $mapping->addForeignKeyConstraint($reference, [self::id($referenceName)], ['id'], $onDelete['cascade'], $fkName);

                        break;
                    }

                    $mapping->addColumn($referenceName . '_version_id', Types::BINARY, $binary);

                    //primary key is build with source_id, reference_id, reference_version_id
                    $mapping->setPrimaryKey([self::id($name), self::id($referenceName), $referenceName . '_version_id']);

                    // add foreign key to source table (custom_entity_blog.id <=> custom_entity_blog_products.custom_entity_blog_id), add cascade delete for both
                    $fkName = substr('fk_ce_' . $mapping->getName() . '_' . $name, 0, 64);
                    $mapping->addForeignKeyConstraint($table, [self::id($name)], ['id'], $onDelete['cascade'], $fkName);

                    // add foreign key to reference table (product.id <=> custom_entity_blog_products.product_id), add cascade delete for both
                    $fkName = substr('fk_ce_' . $mapping->getName() . '_' . $referenceName, 0, 64);
                    $mapping->addForeignKeyConstraint($reference, [self::id($referenceName), $referenceName . '_version_id'], ['id', 'version_id'], $onDelete['cascade'], $fkName);

                    break;
                case 'many-to-one':
                case 'one-to-one':
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
                        $fkName = substr('fk_ce_' . $table->getName() . '_' . $field['name'], 0, 64);
                        $table->addForeignKeyConstraint($reference, [self::id($field['name']), $field['name'] . '_version_id'], ['id', 'version_id'], $options, $fkName);

                        break;
                    }

                    // add foreign key to reference table
                    $fkName = substr('fk_ce_' . $table->getName() . '_' . $field['name'], 0, 64);
                    $table->addForeignKeyConstraint($reference, [self::id($field['name'])], ['id'], $options, $fkName);

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

    /**
     * @param CustomEntityField $field
     */
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
}
