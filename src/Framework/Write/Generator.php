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

namespace Shopware\Framework\Write;

use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Identifier;
use Doctrine\DBAL\Schema\Index;
use ReadGenerator\Util;

require_once __DIR__ . '/../../../dev-ops/read-generator/Util.php';

class Generator
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
        @exec('rm -R ' . __DIR__ . '/../../**/Gateway/WriteResource');
        @exec('rm -R ' . __DIR__ . '/../../**/Writer/ResourceDefinition');
        @exec('rm -R ' . __DIR__ . '/../../**/Writer/WriteResource');
        @exec('rm -R ' . __DIR__ . '/../../**/Writer/*WriteResource.php');
        @exec('rm -R ' . __DIR__ . '/../../**/Event/*WrittenEvent.php');
        @exec('rm -R ' . __DIR__ . '/WriteResource/*.php');

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

        /** @var ResourceTemplate $resource */
        foreach ($resources as $table => $resource) {
            @mkdir($resource->getPath(), 0777, true);
            @mkdir($resource->getEventPath(), 0777, true);

            file_put_contents(
                $resource->getPath() . '/' . $resource->getClassName() . '.php',
                $resource->renderClass($table)
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

            if (array_key_exists($column->getName(), $foreignKeys)) {
                continue;
            }

            $writableColumn = $this->makeWritableColumn($column, $table, $this->isPrimary($column, $indexes));

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
            $resourceTemplate->addOrder('\\' . $foreignResource->getNamespace() . '\\' . $foreignResource->getClassName());
        }

        $resourceTemplate->addOrder('\\' . $resourceTemplate->getNamespace() . '\\' . $resourceTemplate->getClassName());

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
                        '\\' . $translationTableResourceTemplate->getNamespace() . '\\' . $translationTableResourceTemplate->getClassName()
                ));
            } else {
                $resourceTemplate->addField(sprintf(
                    '$this->fields[\'translations\'] = new SubresourceField(%s::class, \'languageUuid\');',
                    '\\' . $translationTableResourceTemplate->getNamespace() . '\\' . $translationTableResourceTemplate->getClassName()
                ));
            }

            $resourceTemplate->addOrder('\\' . $translationTableResourceTemplate->getNamespace() . '\\' . $translationTableResourceTemplate->getClassName());
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
                '\\' . $resourceTemplate->getNamespace() . '\\' . $resourceTemplate->getClassName()
            ));

            $otherResourceTemplate->addOrder('\\' . $resourceTemplate->getNamespace() . '\\' . $resourceTemplate->getClassName());
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
                '\\' . $foreignResource->getNamespace() . '\\' . $foreignResource->getClassName()
            ),
            sprintf('$this->%s[\'%s\'] = (new FkField(\'%s\', %s::class, \'%s\'))%s;',
                $fieldProperty,
                FieldName::getFieldName($column->getName(), $tableName),
                $column->getName(),
                '\\' . $foreignResource->getNamespace() . '\\' . $foreignResource->getClassName(),
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
                '\\' . $translationResource->getNamespace() . '\\' . $translationResource->getClassName()
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
use Shopware\Framework\Write\Flag\Required;
use Shopware\Framework\Write\Field\FkField;
use Shopware\Framework\Write\Field\IntField;
use Shopware\Framework\Write\Field\ReferenceField;
use Shopware\Framework\Write\Field\StringField;
use Shopware\Framework\Write\Field\BoolField;
use Shopware\Framework\Write\Field\DateField;
use Shopware\Framework\Write\Field\SubresourceField;
use Shopware\Framework\Write\Field\LongTextField;
use Shopware\Framework\Write\Field\LongTextWithHtmlField;
use Shopware\Framework\Write\Field\FloatField;
use Shopware\Framework\Write\Field\TranslatedField;
use Shopware\Framework\Write\Field\UuidField;
use Shopware\Framework\Write\WriteResource;

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
    
    public static function createWrittenEvent(array $updates, TranslationContext $context, array $errors = []): \%s\Event\%sWrittenEvent
    {
        $event = new \%s\Event\%sWrittenEvent($updates[self::class] ?? [], $context, $errors);

        unset($updates[self::class]);

%s
        return $event;
    }%s
}

EOD;

    private $eventBodyTemplate = <<<'EOD'
        if (!empty($updates[%s])) {
            $event->addEvent(%s::createWrittenEvent($updates, $context));
        }

EOD;

    private $defaultDateMethod = <<<'EOD'
EOD;

    private $serviceDefinitionTemplate = <<<'EOD'
        <service id="shopware.%s.%s.resource" class="%s">
            <tag name="shopware.framework.write.resource"/> 
        </service>
EOD;

    private $eventClassTemplate = <<<'EOD'
<?php declare(strict_types=1);

namespace %s\Event;

use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\Event\NestedEventCollection;
use Shopware\Context\Struct\TranslationContext;

class %sWrittenEvent extends NestedEvent
{
    const NAME = '%s.written';

    /**
     * @var string[]
     */
    protected $%sUuids;

    /**
     * @var NestedEventCollection
     */
    protected $events;

    /**
     * @var array
     */
    protected $errors;
    
    /**
     * @var TranslationContext
     */
    protected $context;

    public function __construct(array $%sUuids, TranslationContext $context, array $errors = [])
    {
        $this->%sUuids = $%sUuids;
        $this->events = new NestedEventCollection();
        $this->context = $context;
        $this->errors = $errors;
    }

    public function getName(): string
    {
        return self::NAME;
    }
    
    public function getContext(): TranslationContext
    {
        return $this->context;
    }

    /**
     * @return string[]
     */
    public function get%sUuids(): array
    {
        return $this->%sUuids;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    public function hasErrors(): bool
    {
        return count($this->errors) > 0;
    }

    public function addEvent(NestedEvent $event): void
    {
        $this->events->add($event);
    }
    
    public function getEvents(): NestedEventCollection
    {
        return $this->events;
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
        $srcDir = __DIR__ . '/../../';
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
        $srcDir = __DIR__ . '/../../';
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

    public function getNamespace(): string
    {
        try {
            $bundleName = $this->getBundleName();
        } catch (\InvalidArgumentException $e) {
            return 'Shopware\\Framework\\Write\\Resource';
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
        return sprintf(
            $this->eventClassTemplate,
            $this->getBundleNamespace(),
            $this->getName(),
            $this->getEventName(),
            lcfirst($this->getName()),
            lcfirst($this->getName()),
            lcfirst($this->getName()),
            lcfirst($this->getName()),
            $this->getName(),
            lcfirst($this->getName())
        );
    }

    public function renderClass($table): string
    {
        $renderedOrder = [];
        foreach (array_unique($this->order) as $classRef) {
            $renderedOrder[] = $classRef . '::class';
        }

        if ($table === 'order') {
            $lineItemIndex = array_search('\Shopware\OrderLineItem\Writer\Resource\OrderLineItemWriteResource::class', $renderedOrder, true);
            $deliveryIndex = array_search('\Shopware\OrderDelivery\Writer\Resource\OrderDeliveryWriteResource::class', $renderedOrder, true);

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

        return sprintf(
            $this->baseClassTemplate,
            $this->getNamespace(),
            $this->getClassName(),
            implode("\n    ", array_unique($this->consts)),
            $this->table,
            implode("\n        ", $clearedFields),
            implode(",\n            ", $renderedOrder),
            $this->getBundleNamespace(),
            $this->getName(),
            $this->getBundleNamespace(),
            $this->getName(),
            $this->getEventBody(array_unique($this->order)),
            $this->hasDates() ? $this->defaultDateMethod : ''
        );
    }

    public function getEventBody($writeOrder): string
    {
        $calls = [];

        foreach ($writeOrder as $resourceClass) {
            $calls[] = sprintf(
                $this->eventBodyTemplate,
                $resourceClass . '::class',
                $resourceClass
            );
        }

        return implode('', $calls);
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

    private function getBundleName(): string
    {
        $srcDir = __DIR__ . '/../../';
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

        throw new \InvalidArgumentException('Us the default, please');
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
