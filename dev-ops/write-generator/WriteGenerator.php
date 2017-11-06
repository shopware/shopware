<?php declare(strict_types=1);
/**
 * Shopware 5
 * Copyright (c) shopware AG
 *
 * According to our dual licensing model, this program can be used either
 * under the terms of the GNU Affero General Public License, version 3,
 * or under a proprietary license.
 *
 * The texts of the GNU Affero General Public License with an additional
 * permission and of our proprietary license can be found at and
 * in the LICENSE file you have received along with this program.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * "Shopware" is a registered trademark of shopware AG.
 * The licensing of the program under the AGPLv3 does not imply a
 * trademark license. Therefore any rights, title and interest in
 * our trademarks remain entirely with us.
 */

use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Identifier;
use Doctrine\DBAL\Schema\Index;
use ReadGenerator\Util;

require_once __DIR__ . '/../read-generator/Util.php';

class WriteGenerator
{
    private $serviceFileTemplate = <<<'EOD'
<?xml version="1.0" ?>

<container xmlns="http://symfony.com/schema/dic/services"
           xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
           xsi:schemaLocation="http://symfony.com/schema/dic/services http://symfony.com/schema/dic/services/services-1.0.xsd">
    <services>
        %s
    </services>
</container>

EOD;

    private $fieldTypeMap = [
        'description_long' => 'LongTextWithHtmlField',
        'active' => 'BoolField',
        'uuid' => 'UuidField',
    ];

    private $ignoreColumnNames = [
        'id',
        'attr1',
        'attr2',
        'attr3',
        'attr4',
        'attr5',
        'attr6',
        'attr7',
        'attr8',
        'attr9',
        'attr10',
        'attr11',
        'attr12',
        'attr13',
        'attr14',
        'attr15',
        'attr16',
        'attr17',
        'attr18',
        'attr19',
        'attr20',
        'created_at',
        'updated_at',
    ];

    private $ignoreTables = [
        's_cart',
    ];

    /**
     * @var \Doctrine\DBAL\Connection
     */
    private $connection;

    public function __construct()
    {
        $connection = \Shopware\Framework\Doctrine\DatabaseConnector::createPdoConnection();

        $this->connection = new \Doctrine\DBAL\Connection(
            ['pdo' => $connection],
            new \Doctrine\DBAL\Driver\PDOMySql\Driver(),
            null,
            null
        );
    }

    public function generateAll()
    {
        @exec('rm -R ' . __DIR__ . '/../../src/**/SqlResourceWriter/Resource/*WriteResource.php');
        @exec('rm -R ' . __DIR__ . '/../../src/**/Writer/Resource/*WriteResource.php');
        @exec('rm -R ' . __DIR__ . '/../../src/**/Event/*WrittenEvent.php');

        $connection = $this->connection;
        $schemaManager = $connection->getSchemaManager();

        $tables = array_filter($schemaManager->listTableNames(), function ($name) {
            return strpos($name, '_attribute') === false && strpos($name, '_ro') === false;
        });
        $tables = array_filter($tables, function ($name) {
            return !in_array($name, $this->ignoreTables, true);
        });

        $resources = [];
        foreach ($tables as $table) {
            $resources[$table] = $this->generateBaseColumns($table);
        }

        foreach ($tables as $table) {
            $resources[$table] = $this->generateForeignKeyColumns($table, $resources);
        }

        $services = [];

        $uses = [];
        /** @var ResourceTemplate $resource */
        foreach ($resources as $resource) {
            if ($resource->getFullClassName() === 'Shopware\Shop\Writer\Resource\ShopWriteResource') {
                continue;
            }
            $uses[] = 'use ' . $resource->getFullClassName() . ';';
            $uses[] = 'use ' . $resource->getFullEventName() . ';';
        }
        $uses = array_unique($uses);

        /** @var ResourceTemplate $resource */
        foreach ($resources as $table => $resource) {
            @mkdir($resource->getPath(), 0777, true);
            @mkdir($resource->getEventPath(), 0777, true);

            file_put_contents(
                $resource->getPath() . '/' . $resource->getClassName() . '.php',
                $resource->renderClass($table, $uses)
            );

            file_put_contents(
                $resource->getEventPath() . '/' . $resource->getName() . 'WrittenEvent.php',
                $resource->renderEventClass()
            );

            $services[$resource->getDiPath()][] = $resource->renderServiceDefinition();
        }

        foreach ($services as $path => $content) {
            @mkdir($path, 0777, true);

            file_put_contents(
                $path . '/write-resources.xml',
                sprintf(
                    $this->serviceFileTemplate,
                    implode("\n", $content)
                )
            );
        }
    }

    public function generateBaseColumns(string $table): ResourceTemplate
    {
        $connection = $this->connection;

        $resourceTemplate = new ResourceTemplate($table);

        // prepare data through schema manager
        $schemaManager = $connection->getSchemaManager();
        $columns = $schemaManager->listTableColumns($table);
        $indexes = $schemaManager->listTableIndexes($table);
        $rawForeignKeys = $schemaManager->listTableForeignKeys($table);

        $foreignKeys = [];
        foreach ($rawForeignKeys as $foreignKey) {
            $foreignKeys[$foreignKey->getLocalColumns()[0]] = [$foreignKey->getForeignTableName(), $foreignKey->getForeignColumns()[0]];
        }

        //maincolumns
        /** @var Column $column */
        foreach ($columns as $column) {
            if (in_array($column->getName(), $this->ignoreColumnNames)) {
                continue;
            }

            $isPrimary = $this->isPrimary($column, $indexes);
            if ($isPrimary) {
                $resourceTemplate->addPrimaryKey($column->getName());
            }

            if (array_key_exists($column->getName(), $foreignKeys)) {
                continue;
            }

            $writableColumn = $this->makeWritableColumn($column, $table, $isPrimary);

            if (!$writableColumn) {
                continue;
            }

            $resourceTemplate->addField($writableColumn);
            $resourceTemplate->addConst(FieldName::getConstDeclaration($column->getName(), $table));
        }

        return $resourceTemplate;
    }

    public function generateForeignKeyColumns(string $table, array $resourceTemplates): ResourceTemplate
    {
        $connection = $this->connection;

        /** @var ResourceTemplate $resourceTemplate */
        $resourceTemplate = $resourceTemplates[$table];

        // prepare data through schema manager
        $schemaManager = $connection->getSchemaManager();
        $columns = $schemaManager->listTableColumns($table);
        $indexes = $schemaManager->listTableIndexes($table);
        $rawForeignKeys = $schemaManager->listTableForeignKeys($table);

        $foreignKeys = [];
        foreach ($rawForeignKeys as $foreignKey) {
            $foreignKeys[$foreignKey->getLocalColumns()[0]] = [$foreignKey->getForeignTableName(), $foreignKey->getForeignColumns()[0]];
        }

        //fk columns
        /** @var Column $column */
        foreach ($columns as $column) {
            if (in_array($column->getName(), $this->ignoreColumnNames)) {
                continue;
            }

            if (!array_key_exists($column->getName(), $foreignKeys)) {
                continue;
            }

            list($foreignTableName, $foreignFieldName) = $foreignKeys[$column->getName()];
            $fkFields = $this->makeForeignKeyColumns($column, $table, $foreignFieldName, $foreignTableName, $resourceTemplates, $this->isPrimary($column, $indexes));

            foreach ($fkFields as $fieldDefinition) {
                $resourceTemplate->addField($fieldDefinition);
            }

            if (!$fkFields) {
                continue;
            }

            $foreignResource = $resourceTemplates[$foreignTableName];
            $resourceTemplate->addOrder($foreignResource->getClassName());
        }

        $resourceTemplate->addOrder($resourceTemplate->getClassName());

        $shopResourceTemplate = $resourceTemplates['shop'];
        /** @var ResourceTemplate $translationTableResourceTemplate */
        $translationTable = $table . '_translation';
        // translationColumns
        if ($schemaManager->tablesExist([$translationTable])) {
            $translationColumns = $schemaManager->listTableColumns($translationTable);
            $translationTableResourceTemplate = $resourceTemplates[$translationTable];

            $hasRequiredFields = false;
            /** @var Column $translationColumn */
            foreach ($translationColumns as $translationColumn) {
                if (in_array($translationColumn->getName(), $this->ignoreColumnNames)) {
                    continue;
                }

                $translationField = $this->makeTranslationColumn($translationColumn, $table, $shopResourceTemplate);

                if (!$translationField) {
                    continue;
                }

                if (!$hasRequiredFields && $translationColumn->getNotnull() && $translationColumn->getDefault() === null) {
                    $hasRequiredFields = true;
                }

                $resourceTemplate->addField($translationField);
                $resourceTemplate->addConst(FieldName::getConstDeclaration($translationColumn->getName(), $table));
            }

            if ($hasRequiredFields) {
                $resourceTemplate->addField(sprintf(
                        '$this->fields[\'translations\'] = (new SubresourceField(%s::class, \'languageUuid\'))->setFlags(new Required());',
                        $translationTableResourceTemplate->getClassName()
                ));
            } else {
                $resourceTemplate->addField(sprintf(
                    '$this->fields[\'translations\'] = new SubresourceField(%s::class, \'languageUuid\');',
                    $translationTableResourceTemplate->getClassName()
                ));
            }

            $resourceTemplate->addOrder($translationTableResourceTemplate->getClassName());
        }

        foreach ($foreignKeys as $foreignData) {
            list($foreignTableName, $foreignFieldName) = $foreignData;

            if (strpos($table, '_translation') !== false) {
                continue;
            }

            if (!isset($resourceTemplates[$foreignTableName])) {
                continue;
            }

            $otherResourceTemplate = $resourceTemplates[$foreignTableName];

            $resourceName = $resourceTemplate->getResourceName($otherResourceTemplate->getTable());
            if ($otherResourceTemplate->getTable() === $table) {
                $plural = 'parent';
            } else {
                $plural = Util::getPlural($resourceName);
            }

            $otherResourceTemplate->addField(sprintf(
                '$this->fields[\'%s\'] = new SubresourceField(%s::class);',
                lcfirst($plural),
                $resourceTemplate->getClassName()
            ));

            $otherResourceTemplate->addOrder($resourceTemplate->getClassName());
        }

        return $resourceTemplate;
    }

    private function isPrimary(Column $column, array $indexes): bool
    {
        if ($column->getName() === 'uuid') {
            return true;
        }

        if (!isset($indexes['primary'])) {
            return false;
        }

        /** @var Index $primaryIndex */
        $primaryIndex = $indexes['primary'];

        /** @var Identifier $indexColumn */
        foreach ($primaryIndex->getColumns() as $indexColumn) {
            if ($indexColumn === $column->getName()) {
                return true;
            }
        }

        return false;
    }

    private function makeForeignKeyColumns(Column $column, string $tableName, string $foreignFieldName, string $foreignTableName, array $foreignResources, bool $isPrimary)
    {
        if (strpos($column->getName(), '_uuid') === false) {
            echo "Error at $tableName::{$column->getName()}\n ";

            return [];
        }

        if (!isset($foreignResources[$foreignTableName])) {
            echo "Error at $tableName::{$column->getName()}\n ";

            return [];
        }

        $foreignResource = $foreignResources[$foreignTableName];
        $withoutUuid = substr($column->getName(), 0, -4);

        $fieldProperty = 'fields';
        if ($isPrimary) {
            $fieldProperty = 'primaryKeyFields';
        }

        $required = false;
        if ($column->getNotnull() && $column->getDefault() === null) {
            $required = true;
        }

        return [
            sprintf('$this->fields[\'%s\'] = new ReferenceField(\'%s\', \'%s\', %s::class);',
                FieldName::getFieldName($withoutUuid, $tableName),
                FieldName::getFieldName($column->getName(), $tableName),
                $this->toCammelCase($foreignFieldName),
                $foreignResource->getClassName()
            ),
            sprintf('$this->%s[\'%s\'] = (new FkField(\'%s\', %s::class, \'%s\'))%s;',
                $fieldProperty,
                FieldName::getFieldName($column->getName(), $tableName),
                $column->getName(),
                $foreignResource->getClassName(),
                $this->toCammelCase($foreignFieldName),
                $required ? '->setFlags(new Required())' : ''
            ),
        ];
    }

    private function makeWritableColumn(Column $column, string $tableName, bool $isPrimary): string
    {
        $columnName = $column->getName();

        switch ($column->getType()) {
            case 'Integer':
                $template = 'IntField';
                break;
            case 'DateTime':
            case 'Date':
                $template = 'DateField';
                break;
            case 'Text':
                $template = 'LongTextField';
                break;
            case 'String':
                $template = 'StringField';
                break;
            case 'Float':
            case 'Decimal':
                $template = 'FloatField';
                break;
            case 'Boolean':
                $template = 'BoolField';
                break;
            default:
                echo "ERROR: unmapped type {$column->getType()}\n";

                return '';
        }

        if (array_key_exists($columnName, $this->fieldTypeMap)) {
            $template = $this->fieldTypeMap[$columnName];
        }

        $stmt = "new $template('{$column->getName()}')";

        if ($column->getNotnull() && $column->getDefault() === null) {
            $stmt = "($stmt)->setFlags(new Required())";
        }

        if ($isPrimary) {
            $stmt = sprintf('$this->primaryKeyFields[%s] = %s;', FieldName::getConstName($column->getName(), $tableName), $stmt);
        } else {
            $stmt = sprintf('$this->fields[%s] = %s;', FieldName::getConstName($column->getName(), $tableName), $stmt);
        }

        return $stmt;
    }

    private function makeTranslationColumn(Column $column, string $tableName, ResourceTemplate $translationResource): string
    {
        if (!in_array($column->getType(), ['Text', 'String'])) {
            return '';
        }

        $columnName = $column->getName();

        if (strpos($columnName, 'uuid') !== false) {
            return '';
        }

        $camelCaseName = $this->toCammelCase($column->getName());

        return sprintf('$this->fields[%s] = new TranslatedField(\'%s\', %s::class, \'uuid\');',
                FieldName::getConstName($column->getName(), $tableName),
                $camelCaseName,
                $translationResource->getClassName()
            );
    }

    private function toCammelCase($value)
    {
        return lcfirst(str_replace(' ', '', ucwords(str_replace('_', ' ', $value))));
    }
}

class FieldName
{
    public static function getFieldName(string $rawName, string $onTableName): string
    {
        if ($rawName !== $onTableName && strpos($rawName, $onTableName) === 0) {
            $rawName = substr($rawName, strlen($onTableName) + 1);
        }

        return self::toCammelCase($rawName);
    }

    public static function getConstName(string $rawName, string $onTableName): string
    {
        if ($rawName !== $onTableName && strpos($rawName, $onTableName) === 0) {
            $rawName = substr($rawName, strlen($onTableName) + 1);
        }

        return 'self::' . strtoupper($rawName) . '_FIELD';
    }

    public static function getConstDeclaration(string $rawName, string $onTableName): string
    {
        if ($rawName !== $onTableName && strpos($rawName, $onTableName) === 0) {
            $rawName = substr($rawName, strlen($onTableName) + 1);
        }

        return 'protected const ' . strtoupper($rawName) . '_FIELD = \'' . self::getFieldName($rawName, $onTableName) . '\';';
    }

    private static function toCammelCase($value): string
    {
        return lcfirst(str_replace(' ', '', ucwords(str_replace('_', ' ', $value))));
    }
}

class ResourceTemplate
{
    private $baseClassTemplate = <<<'EOD'
<?php declare(strict_types=1);

namespace %s;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Api\Write\Flag\Required;
use Shopware\Api\Write\Field\FkField;
use Shopware\Api\Write\Field\IntField;
use Shopware\Api\Write\Field\ReferenceField;
use Shopware\Api\Write\Field\StringField;
use Shopware\Api\Write\Field\BoolField;
use Shopware\Api\Write\Field\DateField;
use Shopware\Api\Write\Field\SubresourceField;
use Shopware\Api\Write\Field\LongTextField;
use Shopware\Api\Write\Field\LongTextWithHtmlField;
use Shopware\Api\Write\Field\FloatField;
use Shopware\Api\Write\Field\TranslatedField;
use Shopware\Api\Write\Field\UuidField;
use Shopware\Api\Write\WriteResource;
use Shopware\Shop\Writer\Resource\ShopWriteResource;

#uses#

class %s extends WriteResource
{
    %s

    public function __construct()
    {
        parent::__construct('%s');
        
        %s
    }
    
    public function getWriteOrder(): array
    {
        return [
            %s
        ];
    }
    
    public static function createWrittenEvent(array $updates, TranslationContext $context, array $rawData = [], array $errors = []): %sWrittenEvent
    {
        $uuids = [];
        if ($updates[self::class]) {
            $uuids = array_column($updates[self::class], 'uuid');
        }
        
        $event = new %sWrittenEvent($uuids, $context, $rawData, $errors);

        unset($updates[self::class]);

        /**
         * @var WriteResource $class
         * @var string[] $identifiers
         */
        foreach ($updates as $class => $identifiers) {
            if (!array_key_exists($class, $updates) || count($updates[$class]) === 0) {
                continue;
            }

            $event->addEvent($class::createWrittenEvent($updates, $context));
        }

        return $event;
    }%s
}

EOD;

    private $defaultDateMethod = <<<'EOD'
EOD;

    private $serviceDefinitionTemplate = <<<'EOD'
        <service id="shopware.%s.%s.resource" class="%s">
            <tag name="shopware.framework.write.resource"/> 
        </service>
EOD;

    private $eventPrimaryKeyTemplate = <<<'EOD'

    /**
     * @var string[]
     */
    protected $#primaryKeyCamelCase#s = [];
    
    public function get#primaryKeyUpperCamelCase#s(): array
    {
        return $this->#primaryKeyCamelCase#s;    
    }
EOD;

    private $eventClassTemplate = <<<'EOD'
<?php declare(strict_types=1);

namespace Shopware\#bundle#\Event;

use Shopware\Api\Write\WrittenEvent;

class #classUc#WrittenEvent extends WrittenEvent
{
    const NAME = '#table#.written';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getEntityName(): string
    {
        return '#table#';
    }
}
EOD;

    /**
     * @var array
     */
    private $fields = [];

    /**
     * @var array
     */
    private $consts = [];

    /**
     * @var string
     */
    private $table;

    private $order = [];

    /**
     * @var string[]
     */
    private $primaryKeys = [];

    public function __construct(string $table)
    {
        $this->table = $table;
    }

    public function addField(string $content)
    {
        $this->fields[] = $content;
    }

    public function addConst(string $content)
    {
        $this->consts[] = $content;
    }

    public function addOrder(string $className)
    {
        $this->order[] = $className;
    }

    /**
     * @return string
     */
    public function getTable(): string
    {
        return $this->table;
    }

    public function getBundlePath(): string
    {
        $srcDir = __DIR__ . '/../../src/';
        try {
            $bundleName = $this->getBundleName();
        } catch (\InvalidArgumentException $e) {
            return $srcDir . 'Framework';
        }

        return $srcDir . $bundleName;
    }

    public function getPath(): string
    {
        return $this->getBundlePath() . '/Writer/Resource';
    }

    public function getEventPath(): string
    {
        return $this->getBundlePath() . '/Event';
    }

    public function getDiPath(): string
    {
        $srcDir = __DIR__ . '/../../src/';
        try {
            $bundleName = $this->getBundleName();
        } catch (\InvalidArgumentException $e) {
            return $srcDir . 'Framework/DependencyInjection';
        }

        return $srcDir . $bundleName . '/DependencyInjection';
    }

    public function getServiceComponentName(): string
    {
        try {
            $bundleName = $this->getBundleName();
        } catch (\InvalidArgumentException $e) {
            return 'write.resource';
        }

        return strtolower($bundleName);
    }

    public function getFullEventName()
    {
        try {
            $bundleName = $this->getBundleName();
        } catch (\InvalidArgumentException $e) {
            return 'Shopware\\Framework\\Event\\' . $this->getName() . 'WrittenEvent';
        }

        return 'Shopware\\' . $bundleName . '\\Event\\' . $this->getName() . 'WrittenEvent';
    }

    public function getFullClassName()
    {
        return $this->getNamespace() . '\\' . $this->getClassName();
    }

    public function getNamespace(): string
    {
        try {
            $bundleName = $this->getBundleName();
        } catch (\InvalidArgumentException $e) {
            return 'Shopware\\Api\\Write\\Resource';
        }

        return 'Shopware\\' . $bundleName . '\\Writer\\Resource';
    }

    public function getBundleNamespace(): string
    {
        try {
            $bundleName = $this->getBundleName();
        } catch (\InvalidArgumentException $e) {
            return 'Shopware\\Framework';
        }

        return 'Shopware\\' . $bundleName;
    }

    public function getName(): string
    {
        $tableName = $this->table;

        if (strpos($tableName, 's_') === 0) {
            $tableName = substr($tableName, 2);
        }

        return ucfirst($this->toCammelCase($tableName));
    }

    public function getClassName(): string
    {
        return $this->getName() . 'WriteResource';
    }

    public function getResourceName(string $inRelationToTable = null): string
    {
        $tableName = $this->table;

        if (strpos($tableName, $inRelationToTable) === 0) {
            $tableName = substr($tableName, strlen($inRelationToTable));
        }

        if (strpos($tableName, 's_') === 0) {
            $tableName = substr($tableName, 2);
        }

        return $this->toCammelCase($tableName);
    }

    public function getServiceName(): string
    {
        $tableName = $this->table;

        if (strpos($tableName, 's_') === 0) {
            $tableName = substr($tableName, 2);
        }

        return $this->toMinusCase($tableName);
    }

    public function getEventName(): string
    {
        $tableName = $this->table;

        if (strpos($tableName, 's_') === 0) {
            $tableName = substr($tableName, 2);
        }

        return strtolower($tableName);
    }

    public function renderEventClass(): string
    {
        return str_replace(
            ['#bundle#', '#classUc#', '#table#'],
            [$this->getBundleName(), $this->getName(), $this->getTable()],
            $this->eventClassTemplate
        );
    }

    public function renderClass($table, array $uses): string
    {
        $renderedOrder = [];
        foreach (array_unique($this->order) as $classRef) {
            $renderedOrder[] = $classRef . '::class';
        }

        if ($table === 'order') {
            $lineItemIndex = array_search('OrderLineItemWriteResource::class', $renderedOrder, true);
            $deliveryIndex = array_search('OrderDeliveryWriteResource::class', $renderedOrder, true);

            if ($lineItemIndex !== false && $deliveryIndex !== false && $lineItemIndex > $deliveryIndex) {
                $tmp = $renderedOrder[$deliveryIndex];
                $renderedOrder[$deliveryIndex] = $renderedOrder[$lineItemIndex];
                $renderedOrder[$lineItemIndex] = $tmp;
            }
        }

        $clearedFields = [];
        foreach (array_unique($this->fields) as $field) {
            if (!$field) {
                continue;
            }

            $clearedFields[] = $field;
        }

        $content = sprintf(
            $this->baseClassTemplate,
            $this->getNamespace(),
            $this->getClassName(),
            implode("\n    ", array_unique($this->consts)),
            $this->table,
            implode("\n        ", $clearedFields),
            implode(",\n            ", $renderedOrder),
            $this->getName(),
            $this->getName(),
            $this->hasDates() ? $this->defaultDateMethod : ''
        );

        $uses = implode("\n", $uses);

        return str_replace('#uses#', $uses, $content);
    }

    public function renderServiceDefinition(): string
    {
        return sprintf(
            $this->serviceDefinitionTemplate,
            $this->getServiceComponentName(),
            $this->getServiceName(),
            $this->getNamespace() . '\\' . $this->getClassName()
        );
    }

    public function hasARequiredField()
    {
        foreach ($this->fields as $field) {
            if (strpos($field, 'new Required()') !== false) {
                return true;
            }
        }

        return false;
    }

    public function addPrimaryKey(string $column): void
    {
        $this->primaryKeys[] = $column;
    }

    private function getBundleName(): string
    {
        $srcDir = __DIR__ . '/../../src/';
        $tableName = $this->table;

        if (strpos($tableName, 's_') === 0) {
            $tableName = substr($tableName, 2);
        }

        $possibleBundleNameParts = explode('_', $tableName);

        for ($i = count($possibleBundleNameParts); $i > 0; --$i) {
            $possibleName = implode('', array_map('ucfirst', array_slice($possibleBundleNameParts, 0, $i)));

            if (is_dir($srcDir . $possibleName)) {
                return $possibleName;
            }
        }

        return 'Framework';
    }

    private function hasDates()
    {
        foreach ($this->consts as $const) {
            if (strpos($const, 'CREATED_AT_FIELD') !== false) {
                return true;
            }
        }

        return false;
    }

    private function toCammelCase($value)
    {
        return lcfirst(str_replace(' ', '', ucwords(str_replace('_', ' ', $value))));
    }

    private function toMinusCase($value)
    {
        return strtolower(str_replace('_', '-', $value));
    }
}
