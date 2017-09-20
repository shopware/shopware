<?php

namespace ReadGenerator\Struct;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Column;
use ReadGenerator\Util;
class Generator
{
    /**
     * @var string
     */
    private $directory;

    /**
     * @var Connection
     */
    private $connection;

    public function __construct(string $directory, Connection $connection)
    {
        $this->directory = $directory;
        $this->connection = $connection;
    }

    public function generate(string $table, array $config)
    {
        $class = Util::snakeCaseToCamelCase($table);

        $columns = $this->connection->getSchemaManager()->listTableColumns($table);

        try {
            $translation = $this->connection->getSchemaManager()->listTableColumns($table.'_translation');
            $translation = array_filter($translation, function(Column $column) use ($table) {
                if ($column->getName() === $table . '_uuid') {
                    return false;
                }
                if ($column->getName() === 'language_uuid') {
                    return false;
                }
                return true;
            });

            $columns = array_merge($columns, $translation);
        } catch (\Exception $e) {
        }

        $properties = $this->createStructProperties($table, $columns, $config);
        $functions = $this->createGetterAndSetters($table, $columns, $config);

        if (array_key_exists('struct_functions', $config)) {
            $functions = array_merge($functions, $config['struct_functions']);
        }

        $associations = Util::getAssociationsForBasicStruct($table, $config);

        $properties = array_merge($properties, $this->createStructAssociationProperties($table, $config, $associations));
        $functions = array_merge($functions, $this->createStructAssociationFunctions($table, $config, $associations));

        $uses = [];
        foreach ($associations as $association) {
            $uses[] = $this->createAssociationStructUsage($association);
        }

        $functions = implode("\n", array_unique($functions));
        $properties = implode("\n", array_unique($properties));
        $uses = implode("\n", array_unique($uses));

        $template = str_replace(
            ['#classUc#', '#properties#', '#functions#', '#uses#'],
            [ucfirst($class), $properties, $functions, $uses],
            file_get_contents(__DIR__.'/templates/struct_template.txt')
        );

        $file = $this->directory.'/'.ucfirst($class).'/Struct/'.ucfirst($class).'BasicStruct.php';

        file_put_contents($file, $template);
    }

    public function generateDetail(string $table, array $config)
    {
        $class = Util::snakeCaseToCamelCase($table);

        $associations = Util::getAssociationsForDetailStruct($table, $config);

        $properties = $this->createStructAssociationProperties($table, $config, $associations);
        $functions = $this->createStructAssociationFunctions($table, $config, $associations);
        $initializer = $this->createStructAssociationInitializer($associations);

        $uses = [];
        foreach ($associations as $association) {
            $use = $this->createAssociationStructUsage($association);
            $uses[] = $use;
        }

        $functions = implode("\n", array_unique($functions));
        $properties = implode("\n", array_unique($properties));
        $uses = implode("\n", array_unique($uses));
        $initializer = implode("\n", array_unique($initializer));


        $template = file_get_contents(__DIR__.'/templates/detail_struct_template.txt');

        if (!empty($initializer)) {
            $template = str_replace('#constructor#', file_get_contents(__DIR__ . '/templates/constructor.txt'), $template);
        }

        $template = str_replace(
            ['#classUc#', '#properties#', '#functions#', '#uses#', '#initializer#'],
            [ucfirst($class), $properties, $functions, $uses, $initializer],
            $template
        );

        $file = $this->directory.'/'.ucfirst($class).'/Struct/'.ucfirst($class).'DetailStruct.php';

        file_put_contents($file, $template);
    }

    private function createStructAssociationInitializer($associations)
    {
        $initializer = [];
        foreach ($associations as $association) {
            if (!Util::isToMany($association['type'])) {
                continue;
            }
            $associationClass = Util::snakeCaseToCamelCase($association['table']);
            $propertyName = Util::getAssociationPropertyName($association);
            $plural = Util::getPlural($propertyName);

            $initializer[] = str_replace(
                ['#plural#', '#classUc#'],
                [lcfirst($plural), ucfirst($associationClass)],
                file_get_contents(__DIR__.'/templates/to_many_association_constructor_initializer.txt')
            );
        }
        return $initializer;
    }


    /**
     * @param Column[] $columns
     * @return array
     */
    private function createStructProperties($table, array $columns, array $config): array
    {
        $properties = [];
        foreach ($columns as $column) {
            $propertyName = Util::getPropertyName($table, $column->getName());
            $type = Util::getType($column);

            if (!$column->getNotnull()) {
                $type .= '|null';
            }

            $columnConfig = [];

            if (array_key_exists($column->getName(), $config['columns'])) {
                $columnConfig = $config['columns'][$column->getName()];
            }

            if (array_key_exists('type', $columnConfig)) {
                $type = $columnConfig['type'];
            }


            $properties[] = str_replace(
                ['#type#', '#name#'],
                [$type, lcfirst($propertyName)],
'
    /**
     * @var #type#
     */
    protected $#name#;'
            );
        }

        return $properties;
    }

    private function createGetterAndSetters(string $table, array $columns, array $config): array
    {
        $functions = [];

        foreach ($columns as $column) {
            $propertyName = Util::getPropertyName($table, $column->getName());

            $type = Util::getType($column);

            if (!$column->getNotnull()) {
                $type = '?' . $type;
            }

            $columnConfig = [];

            if (array_key_exists($column->getName(), $config['columns'])) {
                $columnConfig = $config['columns'][$column->getName()];
            }

            if (array_key_exists('type', $columnConfig)) {
                $type = $columnConfig['type'];
            }

            if (array_key_exists('functions', $columnConfig)) {
                $functions[] = $columnConfig['functions'];
                continue;
            }

            $functions[] = str_replace(
                ['#nameUc#', '#nameLc#', '#type#'],
                [ucfirst($propertyName), lcfirst($propertyName), $type],
                file_get_contents(__DIR__ . '/templates/struct_property_getter_setter.txt')
            );
        }

        return $functions;
    }

    private function createStructAssociationProperties($table, $config, $associations)
    {
        $properties = [];

        foreach ($associations as $association) {
            $associationClass = Util::snakeCaseToCamelCase($association['table']);
            $propertyName = Util::getAssociationPropertyName($association);
            $nullable = $association['nullable'] ? '|null' : '';

            switch (true) {
                case (Util::isToOne($association['type'])):

                    $properties[] = str_replace(
                        ['#associationClassUc#', '#associationClassLc#', '#nullable#'],
                        [ucfirst($associationClass), $propertyName, $nullable],
'
    /**
     * @var #associationClassUc#BasicStruct#nullable#
     */
    protected $#associationClassLc#;'
                    );
                    break;

                case ($association['type'] === Util::ONE_TO_MANY):
                    $plural = Util::getPlural($propertyName);
                    $properties[] = str_replace(
                        ['#associationClassUc#', '#plural#'],
                        [ucfirst($associationClass), lcfirst($plural)],
                        '
    /**
     * @var #associationClassUc#BasicCollection
     */
    protected $#plural#;
'
                    );
                    break;

                case ($association['type'] === Util::MANY_TO_MANY):
                    $plural = Util::getPlural($propertyName);
                    $properties[] = str_replace(
                        ['#classLc#'],
                        [lcfirst($propertyName)],
'
    /**
     * @var string[]
     */
    protected $#classLc#Uuids = [];
'
                    );
                    $properties[] = str_replace(
                        ['#associationClassUc#', '#plural#'],
                        [ucfirst($associationClass), lcfirst($plural)],
'
    /**
     * @var #associationClassUc#BasicCollection
     */
    protected $#plural#;
'
                    );
                    break;
            }
        }

        return array_unique($properties);
    }

    private function createStructAssociationFunctions(string $table, array $config, array $associations)
    {
        $properties = [];

        foreach ($associations as $association) {
            $associationClass = Util::snakeCaseToCamelCase($association['table']);
            $propertyName = Util::getAssociationPropertyName($association);

            $nullable = $association['nullable']? '?': '';
            switch (true) {
                case (Util::isToOne($association['type'])):
                    $properties[] = str_replace(
                        ['#propertyNameUc#', '#propertyNameLc#', '#associationClassUc#', '#nullable#'],
                        [ucfirst($propertyName), lcfirst($propertyName), ucfirst($associationClass), $nullable],
                        file_get_contents(__DIR__ . '/templates/to_one_association_property_getter_and_setter.txt')
                    );
                    break;

                case (Util::MANY_TO_MANY === $association['type']):
                    $plural = Util::getPlural($propertyName);
                    $properties[] = str_replace(
                        ['#classLc#', '#classUc#'],
                        [lcfirst($propertyName), ucfirst($propertyName)],
                        file_get_contents(__DIR__ . '/templates/many_to_many_uuid_functions.txt')
                    );
                    $properties[] = str_replace(
                        ['#associationClassUc#', '#plural#', '#pluralUc#'],
                        [ucfirst($associationClass), lcfirst($plural), ucfirst($plural)],
                        file_get_contents(__DIR__ . '/templates/to_many_association_property_getter_and_setter.txt')
                    );
                    break;

                case (Util::ONE_TO_MANY === $association['type']):
                    $plural = Util::getPlural($propertyName);
                    $properties[] = str_replace(
                        ['#associationClassUc#', '#plural#', '#pluralUc#'],
                        [ucfirst($associationClass), lcfirst($plural), ucfirst($plural)],
                        file_get_contents(__DIR__ . '/templates/to_many_association_property_getter_and_setter.txt')
                    );
                    break;

            }
        }

        return array_unique($properties);
    }

    private function createAssociationStructUsage(array $association): string
    {
        $associationClass = Util::snakeCaseToCamelCase($association['table']);

        if (Util::isToMany($association['type'])) {
            return str_replace(
                ['#associationClassUc#'],
                [ucfirst($associationClass)],
                'use Shopware\#associationClassUc#\Struct\#associationClassUc#BasicCollection;'
            );
        }

        return str_replace(
            ['#associationClassUc#'],
            [ucfirst($associationClass)],
            'use Shopware\#associationClassUc#\Struct\#associationClassUc#BasicStruct;'
        );
    }

}