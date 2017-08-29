<?php declare(strict_types=1);

namespace Shopware\Framework\Api2;

use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Identifier;
use Doctrine\DBAL\Schema\Index;
use Symfony\Component\DependencyInjection\Container;

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

    /**
     * @var Container
     */
    private $container;

    private $fieldTypeMap = [
        'description_long' => 'LongTextWithHtmlField',
        'active' => 'BoolField',
        'uuid' => 'UuidField'
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
        'main_detail_uuid',
    ];

    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    public function generateAll()
    {
        $path = __DIR__ . '/../../Product/Writer/ResourceDefinition';
        @mkdir($path, 0777, true);

        $connection = $this->container->get('dbal_connection');
        $schemaManager = $connection->getSchemaManager();

        $tables = array_filter($schemaManager->listTableNames(), function($name) {
            return false === strpos($name, '_attributes');
        });

        $resources = [];
        foreach ($tables as $table) {
            $resources[$table] = $this->generateBaseColumns($table);
        }

        foreach($tables as $table) {
            $resources[$table] = $this->generateForeignKeyColumns($table, $resources);
        }

        $services = [];

        /** @var ResourceTemplate $resource */
        foreach($resources as $resource) {
            @mkdir($resource->getPath(), 0777, true);

            file_put_contents(
                $resource->getPath() . '/' . $resource->getClassName() . '.php',
                $resource->renderClass()
            );

            $services[$resource->getDiPath()][] = $resource->renderServiceDefinition();
        }

        foreach($services as $path => $content) {
            file_put_contents(
                $path . '/api2-resources.xml',
                sprintf(
                    $this->serviceFileTemplate,
                    implode("\n", $content)
                )
            );
        }
    }

    public function generateBaseColumns(string $table): ResourceTemplate
    {
        $connection = $this->container->get('dbal_connection');

        $resourceTemplate = new ResourceTemplate($table);

        // prepare data through schema manager
        $schemaManager = $connection->getSchemaManager();
        $columns = $schemaManager->listTableColumns($table);
        $indexes = $schemaManager->listTableIndexes($table);
        $rawForeignKeys = $schemaManager->listTableForeignKeys($table);

        $foreignKeys = [];
        foreach($rawForeignKeys as $foreignKey) {
            $foreignKeys[$foreignKey->getLocalColumns()[0]] = [$foreignKey->getForeignTableName(), $foreignKey->getForeignColumns()[0]];
        }

        //maincolumns
        /** @var Column $column */
        foreach($columns as $column) {
            if(in_array($column->getName(), $this->ignoreColumnNames)) {
                continue;
            }

            if(array_key_exists($column->getName(), $foreignKeys)) {
                continue;
            }

            $resourceTemplate->addField(
                $this->makeWritableColumn($column, $table, $this->isPrimary($column, $indexes))
            );
        }

        return $resourceTemplate;
    }

    public function generateForeignKeyColumns(string $table, array $resourceTemplates): ResourceTemplate
    {
        $connection = $this->container->get('dbal_connection');

        /** @var ResourceTemplate $resourceTemplate */
        $resourceTemplate = $resourceTemplates[$table];

        // prepare data through schema manager
        $schemaManager = $connection->getSchemaManager();
        $columns = $schemaManager->listTableColumns($table);
        $indexes = $schemaManager->listTableIndexes($table);
        $rawForeignKeys = $schemaManager->listTableForeignKeys($table);

        $foreignKeys = [];
        foreach($rawForeignKeys as $foreignKey) {
            $foreignKeys[$foreignKey->getLocalColumns()[0]] = [$foreignKey->getForeignTableName(), $foreignKey->getForeignColumns()[0]];
        }

        //fk columns
        /** @var Column $column */
        foreach($columns as $column) {
            if(in_array($column->getName(), $this->ignoreColumnNames)) {
                continue;
            }

            if(!array_key_exists($column->getName(), $foreignKeys)) {
                continue;
            }

            list($foreignTableName, $foreignFieldName) = $foreignKeys[$column->getName()];
            $fkFields = $this->makeForeignKeyColumns($column, $table, $foreignFieldName, $foreignTableName, $resourceTemplates, $this->isPrimary($column, $indexes));

            foreach($fkFields as $fieldDefinition) {
                $resourceTemplate->addField($fieldDefinition);
            }

            if(!$fkFields) {
                continue;
            }

            $foreignResource = $resourceTemplates[$foreignTableName];
            $resourceTemplate->addOrder('\\' . $foreignResource->getNamespace() . '\\' . $foreignResource->getClassName());

        }

        $resourceTemplate->addOrder('\\' . $resourceTemplate->getNamespace() . '\\' . $resourceTemplate->getClassName());

        $shopResourceTemplate = $resourceTemplates['s_core_shops'];
        /** @var ResourceTemplate $translationTableResourceTemplate */
        $translationTable = $table . '_translation';
        // translationColumns
        if($schemaManager->tablesExist([$translationTable])) {
            $translationColumns = $schemaManager->listTableColumns($translationTable);
            $translationTableResourceTemplate = $resourceTemplates[$translationTable];

            foreach($translationColumns as $translationColumn) {
                if(in_array($translationColumn->getName(), $this->ignoreColumnNames)) {
                    continue;
                }

                $resourceTemplate->addField($this->makeTranslationColumn($translationColumn, $shopResourceTemplate));
            }

            if($translationTableResourceTemplate->hasARequiredField()) {
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

        foreach($foreignKeys as $foreignData) {
            list($foreignTableName, $foreignFieldName) = $foreignData;

            if(false !== strpos($table, '_translation')) {
                continue;
            }

            if(!isset($resourceTemplates[$foreignTableName])) {
                continue;
            }

            $otherResourceTemplate = $resourceTemplates[$foreignTableName];

            $otherResourceTemplate->addField(sprintf(
                '$this->fields[\'%s\'] = new SubresourceField(%s::class);',
                $resourceTemplate->getResourceName($otherResourceTemplate->getTable()) . 's',
                '\\' . $resourceTemplate->getNamespace() . '\\' . $resourceTemplate->getClassName()
            ));

            $otherResourceTemplate->addOrder('\\' . $resourceTemplate->getNamespace() . '\\' . $resourceTemplate->getClassName());
        }

        return $resourceTemplate;
    }

    private function isPrimary(Column $column, array $indexes): bool {
        if($column->getName() === 'uuid') {
            return true;
        }

        if(!isset($indexes['primary'])) {
            return false;
        }

        /** @var Index $primaryIndex */
        $primaryIndex = $indexes['primary'];

        /** @var Identifier $indexColumn */
        foreach($primaryIndex->getColumns() as $indexColumn) {
            if($indexColumn === $column->getName()) {
                return true;
            }
        }

        return false;
    }

    private function makeForeignKeyColumns(Column $column, string $tableName, string $foreignFieldName, string $foreignTableName, array $foreignResources, bool $isPrimary)
    {
        if(strpos($column->getName(), '_uuid') === false) {
            echo "Error at $tableName::{$column->getName()}\n ";
            return [];
        }

        if(!isset($foreignResources[$foreignTableName])) {
            echo "Error at $tableName::{$column->getName()}\n ";
            return [];
        }

        $foreignResource = $foreignResources[$foreignTableName];
        $withoutUuid = substr($column->getName(), 0, -4);

        $fieldProperty = 'fields';
        if($isPrimary) {
            $fieldProperty = 'primaryKeyFields';
        }

        return [
            sprintf('$this->fields[\'%s\'] = new ReferenceField(\'%s\', \'%s\', %s::class);',
                FieldName::getFieldName($withoutUuid, $tableName),
                FieldName::getFieldName($column->getName(), $tableName),
                $this->toCammelCase($foreignFieldName),
                '\\' . $foreignResource->getNamespace() . '\\' . $foreignResource->getClassName()
            ),
            sprintf('$this->%s[\'%s\'] = (new FkField(\'%s\', %s::class, \'%s\'))->setFlags(new Required());',
                $fieldProperty,
                FieldName::getFieldName($column->getName(), $tableName),
                $column->getName(),
                '\\' . $foreignResource->getNamespace() . '\\' . $foreignResource->getClassName(),
                $this->toCammelCase($foreignFieldName)
            )

        ];
    }

    private function makeWritableColumn(Column $column, string $tableName, bool $isPrimary): string
    {
        $columnName = $column->getName();

        switch($column->getType()) {
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

        if(array_key_exists($columnName, $this->fieldTypeMap)) {
            $template = $this->fieldTypeMap[$columnName];
        }

        $stmt = "new $template('{$column->getName()}')";

//        if(in_array($columnName, ['created_at', 'updated_at'])) {
//            if('created_at' === $columnName) {
//                $contents[] = '    ->setDefaultOnInsert()';
//            } else {
//                $contents[] = '    ->setDefaultOnUpdate()';
//            }
//
//            $contents[] = '    ->fromTemplate(NowDefaultValueTemplate::class)';
//        }

        if($column->getNotnull() && null === $column->getDefault()) {
            $stmt = "($stmt)->setFlags(new Required())";
        }

        if($isPrimary) {
            $stmt = sprintf('$this->primaryKeyFields[\'%s\'] = %s;', FieldName::getFieldName($column->getName(), $tableName), $stmt);
        } else {
            $stmt = sprintf('$this->fields[\'%s\'] = %s;', FieldName::getFieldName($column->getName(), $tableName), $stmt);
        }

        return $stmt;
    }

    private function makeTranslationColumn(Column $column, ResourceTemplate $translationResource): string
    {
        if(!in_array($column->getType(), ['Text', 'String'])) {
            return '';
        }

        $columnName = $column->getName();

        if(false !== strpos($columnName, 'uuid')) {
            return '';
        }

        $camelCaseName = $this->toCammelCase($column->getName());

        return sprintf('$this->fields[\'%s\'] = new TranslatedField(\'%s\', %s::class, \'uuid\');',
                $camelCaseName,
                $camelCaseName,
                '\\' . $translationResource->getNamespace() . '\\' . $translationResource->getClassName()
            );
    }

    private function toCammelCase($value)
    {
        return lcfirst(str_replace(' ', '', ucwords(str_replace('_', ' ', $value))));
    }

    private function toMinusCase($value)
    {
        return str_replace('_', '-', $value);
    }
}

class FieldName {
    public static function getFieldName(string $rawName, string $onTableName): string
    {
        if(0 === strpos($rawName, $onTableName)) {
            $rawName = substr($rawName, strlen($onTableName) + 1);
        }

        return self::toCammelCase($rawName);
    }

    private static function toCammelCase($value): string
    {
        return lcfirst(str_replace(' ', '', ucwords(str_replace('_', ' ', $value))));
    }
}

class Pathfinder {
    /**
     * @var string
     */
    private $srcPath;
    /**
     * @var string
     */
    private $frameworkPath;

    public function __construct(string $srcPath, string $frameworkPath)
    {
        $this->srcPath = $srcPath;
        $this->frameworkPath = $frameworkPath;
    }

    public function getPathForBaseOnTable(): string
    {

    }
}

class ResourceTemplate
{
    private $baseClassTemplate = <<<'EOD'
<?php declare(strict_types=1);

namespace %s;

use Shopware\Framework\Api2\ApiFlag\Required;
use Shopware\Framework\Api2\Field\FkField;
use Shopware\Framework\Api2\Field\IntField;
use Shopware\Framework\Api2\Field\ReferenceField;
use Shopware\Framework\Api2\Field\StringField;
use Shopware\Framework\Api2\Field\BoolField;
use Shopware\Framework\Api2\Field\DateField;
use Shopware\Framework\Api2\Field\SubresourceField;
use Shopware\Framework\Api2\Field\LongTextField;
use Shopware\Framework\Api2\Field\LongTextWithHtmlField;
use Shopware\Framework\Api2\Field\FloatField;
use Shopware\Framework\Api2\Field\TranslatedField;
use Shopware\Framework\Api2\Field\UuidField;
use Shopware\Framework\Api2\Resource\ApiResource;

class %s extends ApiResource
{
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
}

EOD;

    private $serviceDefinitionTemplate = <<<'EOD'
<service id="shopware.%s.%s.resource" class="%s">
    <tag name="shopware.framework.api2.resource"/> 
</service>
EOD;

    /**
     * @var array
     */
    private $fields = [];

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

    public function addOrder(string $className) {
        $this->order[] = $className;
    }

    /**
     * @return string
     */
    public function getTable(): string
    {
        return $this->table;
    }

    public function getPath(): string
    {
        $srcDir = __DIR__ . '/../../';
        $tableName = $this->table;

        if(strpos($tableName, 's_') === 0) {
            $tableName = substr($tableName, 2);
        }

        $bundleName = ucfirst(explode('_', $tableName)[0]);

        if(is_dir($srcDir . $bundleName)) {
            return $srcDir . $bundleName . '/Gateway/Resource';
        }

        return $srcDir . 'Framework/Api2/Resource';
    }

    public function getDiPath(): string
    {
        $srcDir = __DIR__ . '/../../';
        $tableName = $this->table;


        $bundleName = ucfirst(explode('_', $tableName)[0]);

        if(is_dir($srcDir . $bundleName)) {
            return $srcDir . $bundleName . '/DependencyInjection';
        }

        return $srcDir . 'Framework/DependencyInjection';
    }

    public function getServiceComponentName(): string
    {
        $srcDir = __DIR__ . '/../../';
        $tableName = $this->table;

        if(strpos($tableName, 's_') === 0) {
            $tableName = substr($tableName, 2);
        }

        $bundleName = ucfirst(explode('_', $tableName)[0]);

        if(is_dir($srcDir . $bundleName)) {
            return strtolower($bundleName);
        }

        return 'api2.resource';
    }

    public function getNamespace(): string
    {
        $srcDir = __DIR__ . '/../../';
        $tableName = $this->table;

        if(strpos($tableName, 's_') === 0) {
            $tableName = substr($tableName, 2);
        }

        $bundleName = ucfirst(explode('_', $tableName)[0]);

        if(is_dir($srcDir . $bundleName)) {
            return 'Shopware\\' . $bundleName . '\\Gateway\\Resource';
        }

        return 'Shopware\\Framework\\Api2\\Resource';
    }

    public function getClassName(): string
    {
        $tableName = $this->table;

        if(strpos($tableName, 's_') === 0) {
            $tableName = substr($tableName, 2);
        }

        return ucfirst($this->toCammelCase($tableName)) . 'Resource';
    }

    public function getResourceName(string $inRelationToTable = null): string
    {
        $tableName = $this->table;

        if(strpos($tableName, $inRelationToTable) === 0) {
            $tableName = substr($tableName, strlen($inRelationToTable));
        }

        if(strpos($tableName, 's_') === 0) {
            $tableName = substr($tableName, 2);
        }

        return $this->toCammelCase($tableName);
    }

    public function getServiceName(): string
    {
        $tableName = $this->table;

        if(strpos($tableName, 's_') === 0) {
            $tableName = substr($tableName, 2);
        }

        return $this->toMinusCase($tableName);
    }

    public function renderClass(): string
    {
        $renderedOrder = [];
        foreach(array_unique($this->order) as $classRef) {
            $renderedOrder[] = $classRef . '::class';
        }

        $clearedFields = [];
        foreach(array_unique($this->fields) as $field) {
            if(!$field) {
                continue;
            }

            $clearedFields[] = $field;
        }

        return sprintf(
            $this->baseClassTemplate,
            $this->getNamespace(),
            $this->getClassName(),
            $this->table,
            implode("\n        ", $clearedFields),
            implode(",\n            ", $renderedOrder)
        );
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
        foreach($this->fields as $field) {
            if(strpos($field, 'new Required()') !== false) {
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