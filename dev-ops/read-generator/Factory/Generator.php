<?php

namespace ReadGenerator\Factory;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Column;
use ReadGenerator\Util;
class Generator
{
    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var string
     */
    private $directory;

    public function __construct(string $directory, Connection $connection)
    {
        $this->connection = $connection;
        $this->directory = $directory;
    }

    public function getFiles($table)
    {
        $class = Util::snakeCaseToCamelCase($table);
        return [
            $this->directory.'/'.ucfirst($class).'/Factory/'.ucfirst($class).'DetailFactory.php',
            $this->directory.'/'.ucfirst($class).'/Factory/'.ucfirst($class).'BasicFactory.php'
        ];
    }

    public function generateDetail(string $table, array $config)
    {
        $basicAssociations = Util::getAssociationsForBasicStruct($table, $config);

        $template = __DIR__ . '/templates/factory_detail.txt';
        $detailAssociations = Util::getAssociationsForDetailStruct($table, $config);

        list($requiredFactories, $join, $properties, $constructor, $uses, $init, $associationAssignments, $class, $functions) = $this->resolveDependencies(
            $table,
            $config,
            $detailAssociations
        );

        $detailFields = $this->getFieldsForAssociations($detailAssociations);

        $parentFactoryDependencyCall = [];
        foreach ($basicAssociations as $association) {
            $associationClass = Util::snakeCaseToCamelCase($association['table']);
            $parentFactoryDependencyCall[] = str_replace(
                ['#classLc#'],
                [lcfirst($associationClass)],
                '$#classLc#Factory'
            );
            $detailFactory = 'shopware.' . $association['table'] . '.detail_factory';

            switch ($association['type']) {
                case Util::MANY_TO_ONE:
                    $associationClass = Util::snakeCaseToCamelCase($association['table']);
                    if (!in_array($detailFactory, $requiredFactories, true)) {
                        $requiredFactories[] = 'shopware.' . $association['table'] . '.basic_factory';
                        $type = 'Basic';
                    } else {
                        $type = 'Detail';
                    }
                    $constructor[] = str_replace(
                        ['#classUc#', '#classLc#', '#type#'],
                        [ucfirst($associationClass), lcfirst($associationClass), $type],
                        '        #classUc##type#Factory $#classLc#Factory'
                    );
                    $uses[] = str_replace(
                        ['#classUc#', '#type#'],
                        [ucfirst($associationClass), $type],
                        'use Shopware\#classUc#\Factory\#classUc##type#Factory;'
                    );
                    break;
                case Util::ONE_TO_MANY:
                    if ($association['has_detail_reader']) {
                        $type = 'Detail';
                        $requiredFactories[] = 'shopware.' . $association['table'] . '.detail_factory';
                    } else {
                        $requiredFactories[] = 'shopware.' . $association['table'] . '.basic_factory';
                        $type = 'Basic';
                    }
                    $associationClass = Util::snakeCaseToCamelCase($association['table']);
                    $uses[] = str_replace(
                        ['#classUc#', '#type#'],
                        [ucfirst($associationClass), ucfirst($type)],
                        'use Shopware\#classUc#\Factory\#classUc##type#Factory;'
                    );

                    $constructor[] = str_replace(
                        ['#classUc#', '#classLc#', '#type#'],
                        [ucfirst($associationClass), lcfirst($associationClass), ucfirst($type)],
                        '        #classUc##type#Factory $#classLc#Factory'
                    );

                    break;
                case Util::MANY_TO_MANY:
                    $requiredFactories[] = 'shopware.' . $association['table'] . '.basic_factory';
                    $associationClass = Util::snakeCaseToCamelCase($association['table']);
                    $uses[] = str_replace(
                        ['#classUc#'],
                        [ucfirst($associationClass)],
                        'use Shopware\#classUc#\Factory\#classUc#BasicFactory;'
                    );

                    $constructor[] = str_replace(
                        ['#classUc#', '#classLc#'],
                        [ucfirst($associationClass), lcfirst($associationClass)],
                        '        #classUc#BasicFactory $#classLc#Factory'
                    );
                    break;
            }
        }

        $allFields = $this->getAllFields($detailAssociations);

        $properties  = implode("\n", array_unique($properties));
        $constructor  = implode(", \n", array_unique($constructor));
        if (!empty($constructor)) {
            $constructor = ",\n" . $constructor;
        }

        $assignments = array_merge([], $associationAssignments);

        $init  = implode("\n", array_unique($init));
        $uses  = implode("\n", array_unique($uses));
        $assignments  = implode("\n", $assignments);
        $join  = implode("\n", $join);
        $detailFields = implode("\n", $detailFields);
        $allFields = implode("\n", $allFields);
        $functions = implode("\n", $functions);
        
        $parentFactoryDependencyCall = implode(', ', array_unique($parentFactoryDependencyCall));
        if (!empty($parentFactoryDependencyCall)) {
            $parentFactoryDependencyCall = ',' . $parentFactoryDependencyCall;
        }

        $content = str_replace(
            ['#uses#', '#classLc#', '#classUc#', '#dependencyFactories#', '#dependencyFactoriesConstructor#', '#dependencyFactoriesInit#', '#joinDepdencies#', '#depencyFields#', '#hydration#', '#parentFactoryDependencyCall#', '#allFields#', '#functions#'],
            [$uses, lcfirst($class), ucfirst($class), $properties, $constructor, $init, $join, $detailFields, $assignments, $parentFactoryDependencyCall, $allFields, $functions],
            file_get_contents($template)
        );

        $file = $this->directory.'/'.ucfirst($class).'/Factory/'.ucfirst($class).'DetailFactory.php';
        file_put_contents($file, $content);
        return $this->createServicesXml($table, $config, $requiredFactories, 'Detail');
    }

    public function generate(string $table, array $config)
    {
        $template = __DIR__ . '/templates/factory.txt';
        $associations = Util::getAssociationsForBasicStruct($table, $config);

        $columns = $this->connection->getSchemaManager()->listTableColumns($table);
        $assignments = $this->createColumnAssignments($table, $config, $columns);

        $fields = [];
        foreach ($columns as $column) {
            $property = Util::getPropertyName($table, $column->getName());
            $fields[] = "       '" . lcfirst($property) . "' => '" . $column->getName() . "'";
        }

        list($requiredFactories, $joins, $properties, $constructor, $uses, $init, $associationAssignments, $class, $functions) = $this->resolveDependencies(
            $table,
            $config,
            $associations
        );

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

            $assignments = array_merge(
                $assignments,
                $this->createColumnAssignments($table, $config, $translation)
            );
            foreach ($translation as $column) {
                $property = Util::getPropertyName($table, $column->getName());
                $fields[] = "       '" . lcfirst($property) . "' => 'translation." . $column->getName() . "'";
            }
            $join = str_replace('#table#', $table, file_get_contents(__DIR__.'/templates/translation_join.txt'));

            $functions[] = str_replace(['#propertyUc#', '#content#'], ['Translation', $join], file_get_contents(__DIR__ . '/templates/join_function.txt'));
            $joins[] = '$this->joinTranslation($selection, $query, $context);';

        } catch (\Exception $e) {
        }

        $basicFields = $this->getFieldsForAssociations($associations);

        $allFields = $this->getAllFields($associations);

        $properties  = implode("\n", array_unique($properties));
        $constructor  = implode(", \n", array_unique($constructor));
        if (!empty($constructor)) {
            $constructor = ",\n" . $constructor;
        }

        $assignments = array_merge($assignments, $associationAssignments);

        $init  = implode("\n", array_unique($init));
        $allFields  = implode("\n", array_unique($allFields));
        $uses  = implode("\n", array_unique($uses));
        $assignments  = implode("\n", $assignments);
        $joins  = implode("\n", $joins);
        $basicFields  = implode("\n", $basicFields);
        $fields = implode(",\n", $fields);
        $functions = implode("\n", $functions);

        $content = str_replace(
            ['#uses#', '#fields#', '#classUc#', '#classLc#', '#rootName#', '#dependencyFactories#', '#dependencyFactoriesConstructor#', '#dependencyFactoriesInit#', '#hydration#', '#joinDepdencies#', '#basicDepency#', '#allFields#', '#functions#'],
            [$uses, $fields, ucfirst($class), lcfirst($class), $table, $properties, $constructor, $init, $assignments, $joins, $basicFields, $allFields, $functions],
            file_get_contents($template)
        );

        $file = $this->directory.'/'.ucfirst($class).'/Factory/'.ucfirst($class).'BasicFactory.php';

        file_put_contents($file, $content);

        return $this->createServicesXml($table, $config, $requiredFactories, 'Basic');

    }

    private function getFieldsForAssociations(array $associations): array
    {
        $fields = [];
        foreach ($associations as $association) {
            $property = Util::getAssociationPropertyName($association);
            $associationClass = Util::snakeCaseToCamelCase($association['table']);

            switch ($association['type']) {
                case Util::ONE_TO_ONE:
                case Util::MANY_TO_ONE:
                    $fields[] = str_replace(
                        ['#propertyLc#', '#classLc#'],
                        [lcfirst($property), lcfirst($associationClass)],
                        file_get_contents(__DIR__.'/templates/dependency_fields.txt')
                    );
                    break;
                case Util::MANY_TO_MANY:
                    $fields[] = str_replace(
                        ['#propertyLc#'],
                        [lcfirst($property)],
                        '        $fields[\'_sub_select_#propertyLc#_uuids\'] = \'_sub_select_#propertyLc#_uuids\';'
                    );
                    break;
            }
        }

        return $fields;
    }


    private function createColumnAssignments(string $table, array $config, array $columns)
    {
        $class = Util::snakeCaseToCamelCase($table);

        $assignments = [];
        foreach ($columns as $column) {
            $propertyName = Util::getPropertyName($table, $column->getName());
            $columnName = $column->getName();

            $type = Util::getType($column);
            $columnConfig = [];

            if (array_key_exists($column->getName(), $config['columns'])) {
                $columnConfig = $config['columns'][$column->getName()];
            }

            if (array_key_exists('type', $columnConfig)) {
                $type = $columnConfig['type'];
            }

            switch ($type) {
                case '\DateTime':
                    $template = __DIR__ . '/templates/hydrator_cast_date.txt';
                    break;
                case 'array':
                    $template = __DIR__ . '/templates/hydrator_cast_array.txt';
                    break;
                default:
                    $template = __DIR__ . '/templates/hydrator_cast.txt';
            }

            $casted = str_replace(
                ['#type#', '#classLc#', '#column#'],
                [$type, lcfirst($class), lcfirst($propertyName)],
                file_get_contents($template)
            );

            $template = __DIR__ . '/templates/hydrator_assignment.txt';
            if (!$column->getNotnull() && $type !== 'array') {
                $template = __DIR__ . '/templates/hydrator_assignment_nullable.txt';
            }

            $assignments[] = str_replace(
                ['#classLc#', '#propertyUc#', '#column#', '#casted#'],
                [lcfirst($class), ucfirst($propertyName), lcfirst($propertyName), $casted],
                file_get_contents($template)
            );
        }

        return array_unique($assignments);
    }

    private function createServicesXml($table, $config, array $associations, string $type)
    {
        $class = Util::snakeCaseToCamelCase($table);

        $factories = [];
        foreach ($associations as $service) {
            $factories[] = str_replace('#service#', $service, '            <argument id="#service#" type="service"/>');
        }
        $factories = implode("\n", array_unique($factories));
        if (!empty($factories)) {
            $factories = "\n" . $factories;
        }
        return str_replace(
            ['#classUc#', '#table#', '#factories#', '#typeLc#', '#typeUc#'],
            [ucfirst($class), $table, $factories, lcfirst($type), ucfirst($type)],
            file_get_contents(__DIR__ . '/templates/services.xml.txt')
        );
    }

    /**
     * @param string $table
     * @param array $config
     * @param $all
     * @return array
     */
    private function resolveDependencies(string $table, array $config, $all): array
    {
        $requiredFactories = [];

        $joins = [];
        $properties = [];
        $constructor = [];
        $uses = [];
        $init = [];
        $functions = [];
        $associationAssignments = [];

        $class = Util::snakeCaseToCamelCase($table);

        foreach ($all as $association) {
            $property = Util::getAssociationPropertyName($association);

            switch ($association['type']) {
                case Util::ONE_TO_ONE:
                    $associationClass = Util::snakeCaseToCamelCase($association['table']);
                    if ($association['has_detail_reader']) {
                        $type = 'Detail';
                        $requiredFactories[] = 'shopware.' . $association['table'] . '.detail_factory';
                    } else {
                        $type = 'Basic';
                        $requiredFactories[] = 'shopware.' . $association['table'] . '.basic_factory';
                    }

                    $join = str_replace(
                        ['#associationClassLc#', '#propertyLc#', '#associationTable#', '#foreignKey#'],
                        [
                            lcfirst($associationClass),
                            lcfirst($property),
                            $association['table'],
                            $association['foreignKeyColumn']
                        ],
                        file_get_contents(__DIR__.'/templates/many_to_one_join.txt')
                    );

                    $functions[] = str_replace(['#propertyUc#', '#content#'], [ucfirst($property), $join], file_get_contents(__DIR__ . '/templates/join_function.txt'));
                    $joins[] = str_replace(
                        ['#propertyUc#'],
                        [ucfirst($property)],
                        '$this->join#propertyUc#($selection, $query, $context);'
                    );

                    $uses[] = str_replace(
                        ['#classUc#', '#type#'],
                        [ucfirst($associationClass), ucfirst($type)],
                        'use Shopware\#classUc#\Factory\#classUc##type#Factory;'
                    );
                    $uses[] = str_replace(
                        ['#classUc#', '#type#'],
                        [ucfirst($associationClass), ucfirst($type)],
                        'use Shopware\#classUc#\Struct\#classUc##type#Struct;'
                    );

                    $constructor[] = str_replace(
                        ['#classUc#', '#classLc#', '#type#'],
                        [ucfirst($associationClass), lcfirst($associationClass), ucfirst($type)],
                        '        #classUc##type#Factory $#classLc#Factory'
                    );
                    $init[] = str_replace(
                        ['#classLc#'],
                        [lcfirst($associationClass)],
                        '        $this->#classLc#Factory = $#classLc#Factory;'
                    );
                    $properties[] = str_replace(
                        ['#classUc#', '#classLc#', '#type#'],
                        [ucfirst($associationClass), lcfirst($associationClass), ucfirst($type)],
                        '
    /**
     * @var #classUc##type#Factory
     */
    protected $#classLc#Factory;                        
                        '
                    );
                    $associationAssignments[] = str_replace(
                        ['#associationClassLc#', '#propertyLc#', '#classUc#', '#classLc#', '#propertyUc#', '#type#'],
                        [
                            lcfirst($associationClass),
                            lcfirst($property),
                            ucfirst($associationClass),
                            lcfirst($class),
                            ucfirst($property),
                            ucfirst($type)
                        ],
                        file_get_contents(__DIR__.'/templates/many_to_one_assignment.txt')
                    );
                    $uses[] = str_replace(
                        ['#classUc#', '#type#'],
                        [ucfirst($associationClass), ucfirst($type)],
                        'use Shopware\#classUc#\Factory\#classUc##type#Factory;'
                    );
                    break;
                case Util::MANY_TO_ONE:
                    $associationClass = Util::snakeCaseToCamelCase($association['table']);
                    $requiredFactories[] = 'shopware.' . $association['table'] . '.basic_factory';

                    if ($association['property'] === 'canonicalUrl') {
                        $join = str_replace(
                            ['#classLc#', '#seoUrlName#'],
                            [lcfirst($class), $config['seo_url_name']],
                            file_get_contents(__DIR__.'/templates/canonical_join.txt')
                        );
                    } else {
                        $join = str_replace(
                            ['#associationClassLc#', '#propertyLc#', '#associationTable#', '#foreignKey#'],
                            [
                                lcfirst($associationClass),
                                lcfirst($property),
                                $association['table'],
                                $association['foreignKeyColumn']
                            ],
                            file_get_contents(__DIR__.'/templates/many_to_one_join.txt')
                        );
                    }

                    $functions[] = str_replace(['#propertyUc#', '#content#'], [ucfirst($property), $join], file_get_contents(__DIR__ . '/templates/join_function.txt'));
                    $joins[] = str_replace(
                        ['#propertyUc#'],
                        [ucfirst($property)],
                        '$this->join#propertyUc#($selection, $query, $context);'
                    );

                    $uses[] = str_replace(
                        ['#classUc#'],
                        [ucfirst($associationClass)],
                        'use Shopware\#classUc#\Factory\#classUc#BasicFactory;'
                    );
                    $uses[] = str_replace(
                        ['#classUc#'],
                        [ucfirst($associationClass)],
                        'use Shopware\#classUc#\Struct\#classUc#BasicStruct;'
                    );

                    $constructor[] = str_replace(
                        ['#classUc#', '#classLc#'],
                        [ucfirst($associationClass), lcfirst($associationClass)],
                        '        #classUc#BasicFactory $#classLc#Factory'
                    );
                    $init[] = str_replace(
                        ['#classLc#'],
                        [lcfirst($associationClass)],
                        '        $this->#classLc#Factory = $#classLc#Factory;'
                    );
                    $properties[] = str_replace(
                        ['#classUc#', '#classLc#'],
                        [ucfirst($associationClass), lcfirst($associationClass)],
                        '
    /**
     * @var #classUc#BasicFactory
     */
    protected $#classLc#Factory;                        
                        '
                    );
                    $associationAssignments[] = str_replace(
                        ['#associationClassLc#', '#propertyLc#', '#classUc#', '#classLc#', '#propertyUc#', '#type#'],
                        [
                            lcfirst($associationClass),
                            lcfirst($property),
                            ucfirst($associationClass),
                            lcfirst($class),
                            ucfirst($property),
                            'Basic'
                        ],
                        file_get_contents(__DIR__.'/templates/many_to_one_assignment.txt')
                    );
                    $uses[] = str_replace(
                        ['#classUc#'],
                        [ucfirst($associationClass)],
                        'use Shopware\#classUc#\Factory\#classUc#BasicFactory;'
                    );
                    break;
                case Util::ONE_TO_MANY:
                    $plural = Util::getPlural($property);

                    if ($association['has_detail_reader']) {
                        $type = 'Detail';
                        $requiredFactories[] = 'shopware.' . $association['table'] . '.detail_factory';
                    } else {
                        $type = 'Basic';
                        $requiredFactories[] = 'shopware.' . $association['table'] . '.basic_factory';
                    }

                    $associationClass = Util::snakeCaseToCamelCase($association['table']);
                    $uses[] = str_replace(
                        ['#classUc#', '#type#'],
                        [ucfirst($associationClass), $type],
                        'use Shopware\#classUc#\Factory\#classUc##type#Factory;'
                    );
                    $join = str_replace(
                        ['#propertyLc#', '#pluralLc#', '#associationTable#', '#foreignKey#', '#associationClassLc#'],
                        [lcfirst($property), lcfirst($plural), $association['table'], $association['foreignKeyColumn'], lcfirst($associationClass)],
                        file_get_contents(__DIR__.'/templates/one_to_many_join.txt')
                    );
                    $functions[] = str_replace(['#propertyUc#', '#content#'], [ucfirst($plural), $join], file_get_contents(__DIR__ . '/templates/join_function.txt'));
                    $joins[] = str_replace(
                        ['#propertyUc#'],
                        [ucfirst($plural)],
                        '$this->join#propertyUc#($selection, $query, $context);'
                    );
                    $constructor[] = str_replace(
                        ['#classUc#', '#classLc#', '#type#'],
                        [ucfirst($associationClass), lcfirst($associationClass), $type],
                        '        #classUc##type#Factory $#classLc#Factory'
                    );
                    $init[] = str_replace(
                        ['#classLc#'],
                        [lcfirst($associationClass)],
                        '        $this->#classLc#Factory = $#classLc#Factory;'
                    );
                    $properties[] = str_replace(
                        ['#classUc#', '#classLc#', '#type#'],
                        [ucfirst($associationClass), lcfirst($associationClass), $type],
                        '
    /**
     * @var #classUc##type#Factory
     */
    protected $#classLc#Factory;                        
                        '
                    );
                    break;
                case Util::MANY_TO_MANY:
                    $requiredFactories[] = 'shopware.' . $association['table'] . '.basic_factory';
                    $associationClass = Util::snakeCaseToCamelCase($association['table']);
                    $plural = Util::getPlural($property);
                    $uses[] = str_replace(
                        ['#classUc#'],
                        [ucfirst($associationClass)],
                        'use Shopware\#classUc#\Factory\#classUc#BasicFactory;'
                    );
                    $associationAssignments[] = str_replace(
                        ['#associationClassUc#', '#associationClassLc#', '#classLc#', '#propertyUc#', '#propertyLc#'],
                        [ucfirst($associationClass), lcfirst($associationClass), lcfirst($class), ucfirst($property), lcfirst($property)],
                        file_get_contents(__DIR__.'/templates/many_to_many_assignment.txt')
                    );
                    $join = str_replace(
                        [
                            '#propertyLc#',
                            '#pluralLc#',
                            '#associationTable#',
                            '#mappingTable#',
                            '#associationClassLc#',
                            '#table#',
                        ],
                        [
                            lcfirst($property),
                            lcfirst($plural),
                            $association['table'],
                            $association['mapping'],
                            lcfirst($associationClass),
                            $table
                        ],
                        file_get_contents(__DIR__.'/templates/many_to_many_join.txt')
                    );
                    $functions[] = str_replace(['#propertyUc#', '#content#'], [ucfirst($plural), $join], file_get_contents(__DIR__ . '/templates/join_function.txt'));
                    $joins[] = str_replace(
                        ['#propertyUc#'],
                        [ucfirst($plural)],
                        '$this->join#propertyUc#($selection, $query, $context);'
                    );

                    $constructor[] = str_replace(
                        ['#classUc#', '#classLc#'],
                        [ucfirst($associationClass), lcfirst($associationClass)],
                        '        #classUc#BasicFactory $#classLc#Factory'
                    );
                    $init[] = str_replace(
                        ['#classLc#'],
                        [lcfirst($associationClass)],
                        '        $this->#classLc#Factory = $#classLc#Factory;'
                    );
                    $properties[] = str_replace(
                        ['#classUc#', '#classLc#'],
                        [ucfirst($associationClass), lcfirst($associationClass)],
                        '
    /**
     * @var #classUc#BasicFactory
     */
    protected $#classLc#Factory;                        
                        '
                    );
                    break;
            }
        }

        return array(
            $requiredFactories,
            $joins,
            $properties,
            $constructor,
            $uses,
            $init,
            $associationAssignments,
            $class,
            $functions
        );
    }

    private function getAllFields(array $associations): array
    {
        $fields = [];
        foreach ($associations as $association) {
            $property = Util::getAssociationPropertyName($association);
            $associationClass = Util::snakeCaseToCamelCase($association['table']);

            switch ($association['type']) {

                case Util::ONE_TO_ONE:
                case Util::MANY_TO_ONE:
                    $fields[] = str_replace(
                        ['#propertyLc#', '#classLc#'],
                        [lcfirst($property), lcfirst($associationClass)],
                        '        $fields[\'#propertyLc#\'] = $this->#classLc#Factory->getAllFields();'
                    );
                    break;

                case Util::ONE_TO_MANY:
                case Util::MANY_TO_MANY:
                    $plural = Util::getPlural($property);
                    $fields[] = str_replace(
                        ['#propertyLc#', '#classLc#'],
                        [lcfirst($plural), lcfirst($associationClass)],
                        '        $fields[\'#propertyLc#\'] = $this->#classLc#Factory->getAllFields();'
                    );
            }
        }

        return $fields;
    }
}