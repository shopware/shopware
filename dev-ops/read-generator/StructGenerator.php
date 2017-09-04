<?php

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Types\Type;

class StructGenerator
{
    const DEFAULT_CONFIG = [
        'create_detail' => false,
        'get_list_by_parent' => false,
        'get_by_parent' => false,
        'parent' => null,
        'columns' => [],
        'associations' => [],
        'search' => [],
    ];
    const TO_ONE = ['N:1', '1:1'];
    const TO_MANY = ['N:N', '1:N'];

    /**
     * @var Connection
     */
    protected $connection;

    /**
     * @var string
     */
    protected $directory;

    public function __construct(Connection $connection, string $directory)
    {
        $this->connection = $connection;
        $this->directory = $directory;
    }

    public function generate(string $table, array $config)
    {
        $config = array_replace_recursive(self::DEFAULT_CONFIG, $config);

        $this->createDirectories($table, $config);
        $this->createStruct($table, $config);
        $this->createCollection($table, $config);

        $services = [];

        $services[] = $this->createHydrator($table, $config);

        $this->createBundle($table);
        
        $this->createEvent($table, $config);
        $services[] = $this->createReader($table, $config, 'Basic');
        $this->createQuery($table, $config);
        $services[] = $this->createLoader($table, $config);
        $services[] = $this->createSearcher($table, $config);
        $this->createSearchResult($table, $config);
        $services[] = $this->createWriter($table, $config);
        $services[] = $this->createRepository($table, $config);

        if ($config['create_detail']) {
            $this->createDetailStruct($table, $config);
            $this->createDetailCollection($table, $config);

            if ($this->getAssociationsForDetailQuery($table, $config)) {
                $services[] = $this->createDetailHydrator($table, $config);
                $this->createDetailQuery($table, $config);
                $services[] = $this->createReader($table, $config, 'Detail');

                $associations = $this->getAssociationsForDetailLoader($table, $config);
                $associations = array_merge($associations, $this->getAssociationsForBasicLoader($table, $config));
                $this->createDetailLoader($table, $config, $associations, __DIR__.'/generator_templates/detail_loader_with_detail_reader.txt');
                $services[] = $this->createDetailLoaderServiceXmlWithDetailReader($table, $associations);
            } else {
                $associations = $this->getAssociationsForDetailLoader($table, $config);
                $this->createDetailLoader($table, $config, $associations, __DIR__.'/generator_templates/detail_loader_with_basic_loader.txt');
                $services[] = $this->createDetailLoaderServiceXmlWithBasicLoader($table, $associations);
            }
            $this->createDetailEvent($table, $config);
        }

        $handlerServices = $this->createDbalHandlers($table, $config);
        $this->createCriteriaParts($table, $config);
        $services = array_merge($services, $handlerServices);

        $this->createServicesXml($table, $config, $services, $handlerServices);
    }

    private function createDirectories($table, $config)
    {
        $class = $this->snakeCaseToCamelCase($table);

        $dirs = [
            $this->directory,
            $this->directory.'/Framework',
            $this->directory.'/Search',
            $this->directory.'/Search/Condition',
            $this->directory.'/Search/Sorting',
            $this->directory.'/Search/Facet',
            $this->directory.'/'.ucfirst($class),
            $this->directory.'/'.ucfirst($class).'/DependencyInjection',
            $this->directory.'/'.ucfirst($class).'/Event',
            $this->directory.'/'.ucfirst($class).'/Loader',
            $this->directory.'/'.ucfirst($class).'/Reader',
            $this->directory.'/'.ucfirst($class).'/Reader/Query',
            $this->directory.'/'.ucfirst($class).'/Searcher',
            $this->directory.'/'.ucfirst($class).'/Searcher/Handler',
            $this->directory.'/'.ucfirst($class).'/Struct',
            $this->directory.'/'.ucfirst($class).'/Writer'
        ];

        foreach ($dirs as $dir) {
            if (!file_exists($dir)) {
                mkdir($dir);
            }
        }
    }

    private function snakeCaseToCamelCase(string $string)
    {
        $explode = explode('_', $string);
        $explode = array_map('ucfirst', $explode);

        return lcfirst(implode($explode));
    }



    private function createDetailStruct(string $table, array $config)
    {
        $class = $this->snakeCaseToCamelCase($table);

        $associations = $this->getAssociationsForDetailStruct($table, $config);

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

        $template = str_replace(
            ['#classUc#', '#properties#', '#functions#', '#uses#', '#initializer#'],
            [ucfirst($class), $properties, $functions, $uses, $initializer],
            file_get_contents(__DIR__.'/generator_templates/detail_struct_template.txt')
        );

        $file = $this->directory.'/'.ucfirst($class).'/Struct/'.ucfirst($class).'DetailStruct.php';

        file_put_contents($file, $template);

    }


    private function createStruct(string $table, array $config)
    {
        $class = $this->snakeCaseToCamelCase($table);

        $columns = $this->connection->getSchemaManager()->listTableColumns($table);

        $properties = $this->createStructProperties($table, $columns, $config);
        $functions = $this->createGetterAndSetters($table, $columns, $config);

        $associations = $this->getAssociationsForBasicStruct($table, $config);

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
            file_get_contents(__DIR__.'/generator_templates/struct_template.txt')
        );

        $file = $this->directory.'/'.ucfirst($class).'/Struct/'.ucfirst($class).'BasicStruct.php';

        file_put_contents($file, $template);
    }

    private function createCollection(string $table, array $config)
    {
        $class = $this->snakeCaseToCamelCase($table);

        $collectiveUuidGetters = $this->createCollectiveUuidGetters($table);

        $associations = $this->getAssociationsForBasicStruct($table, $config);
        $associationGetters = $this->createCollectionAssociationGetters($table, $associations, 'Basic');

        $uses = [];
        foreach ($associations as $association) {
            $associationClass = $this->snakeCaseToCamelCase($association['table']);
            $uses[] = str_replace(
                ['#classUc#'],
                [ucfirst($associationClass)],
                file_get_contents(__DIR__.'/generator_templates/use_basic_collection.txt')
            );
        }

        $collectivGetters = array_merge($collectiveUuidGetters, $associationGetters);
        $collectivGetters = implode("\n", $collectivGetters);

        $uses = implode("\n", array_unique($uses));

        $template = str_replace(
            ['#classUc#', '#classLc#', '#collectiveUuidGetters#', '#uses#'],
            [ucfirst($class), lcfirst($class), $collectivGetters, $uses],
            file_get_contents(__DIR__.'/generator_templates/collection.txt')
        );

        $file = $this->directory.'/'.ucfirst($class).'/Struct/'.ucfirst($class).'BasicCollection.php';
        file_put_contents($file, $template);
    }

    private function createDetailCollection(string $table, array $config)
    {
        $class = $this->snakeCaseToCamelCase($table);

        $associations = $this->getAssociationsForDetailStruct($table, $config);
        $associationGetters = $this->createCollectionAssociationGetters($table, $associations, 'Detail');

        $uses = [];
        foreach ($associations as $association) {
            $associationClass = $this->snakeCaseToCamelCase($association['table']);
            $uses[] = str_replace(
                ['#classUc#'],
                [ucfirst($associationClass)],
                file_get_contents(__DIR__.'/generator_templates/use_basic_collection.txt')
            );
        }

        $associationGetters = implode("\n", $associationGetters);
        $uses = implode("\n", array_unique($uses));

        $template = str_replace(
            ['#classUc#', '#classLc#', '#collectiveUuidGetters#', '#uses#'],
            [ucfirst($class), lcfirst($class), $associationGetters, $uses],
            file_get_contents(__DIR__.'/generator_templates/detail_collection.txt')
        );

        $file = $this->directory.'/'.ucfirst($class).'/Struct/'.ucfirst($class).'DetailCollection.php';
        file_put_contents($file, $template);
    }

    private function getAssociatedBasicEventUsages($associations)
    {
        $uses = [];
        foreach ($associations as $association) {
            $associationClass = $this->snakeCaseToCamelCase($association['table']);
            $uses[] = str_replace(
                ['#classUc#'],
                [ucfirst($associationClass)],
                file_get_contents(__DIR__.'/generator_templates/use_basic_event.txt')
            );
        }
        return $uses;
    }

    private function getAssociatedBasicEvent($plural, $associations)
    {
        $events = [];

        foreach ($associations as $association) {
            $associationClass = $this->snakeCaseToCamelCase($association['table']);
            $property = $this->getAssociationPropertyName($association);
            $associationPlural = $this->getPlural($property);

            $events[] = str_replace(
                ['#pluralLc#', '#accociationClassUc#', '#associationPluralUc#'],
                [lcfirst($plural), ucfirst($associationClass), ucfirst($associationPlural)],
                file_get_contents(__DIR__.'/generator_templates/nested_basic_event.txt')
            );
        }
        return $events;
    }

    private function createEvent(string $table, array $config)
    {
        $class = $this->snakeCaseToCamelCase($table);
        $plural = $this->getPlural($class);

        $associations = $this->getAssociationsForBasicStruct($table, $config);
        $events = $this->getAssociatedBasicEvent($plural, $associations);
        $uses = $this->getAssociatedBasicEventUsages($associations);

        $events = implode(",\n", $events);
        $uses = implode("\n", array_unique($uses));

        $template = str_replace(
            ['#classUc#', '#classLc#', '#pluralLc#', '#pluralUc#', '#events#', '#uses#'],
            [ucfirst($class), lcfirst($class), lcfirst($plural), ucfirst($plural), $events, $uses],
            file_get_contents(__DIR__.'/generator_templates/event.txt')
        );

        $file = $this->directory.'/'.ucfirst($class).'/Event/'.ucfirst($class).'BasicLoadedEvent.php';
        file_put_contents($file, $template);
    }

    private function createDetailEvent(string $table, array $config)
    {
        $class = $this->snakeCaseToCamelCase($table);
        $plural = $this->getPlural($class);

        $associations = $this->getAssociationsForDetailStruct($table, $config);
        $events = $this->getAssociatedBasicEvent($plural, $associations);
        $uses = $this->getAssociatedBasicEventUsages($associations);

        $events = implode(",\n", $events);
        $uses = implode("\n", array_unique($uses));

        if (!empty($events)) {
            $events = ",\n" . $events;
        }

        $template = str_replace(
            ['#classUc#', '#classLc#', '#pluralLc#', '#pluralUc#', '#events#', '#uses#'],
            [ucfirst($class), lcfirst($class), lcfirst($plural), ucfirst($plural), $events, $uses],
            file_get_contents(__DIR__.'/generator_templates/detail_event.txt')
        );

        $file = $this->directory.'/'.ucfirst($class).'/Event/'.ucfirst($class).'DetailLoadedEvent.php';
        file_put_contents($file, $template);
    }



    private function createDetailHydrator(string $table, array $config)
    {
        $class = $this->snakeCaseToCamelCase($table);

        $associations = $this->getAssociationsForDetailQuery($table, $config);

        $uses = $this->getAssociatedHydratorUsages($associations);
        $properties = $this->getAssociatedHydratorProperties($associations);
        $constructorParameters = $this->getAssociatedHydratorConstructorParameters($associations);
        $constructorInitializer = $this->getAssociatedHydratorConstructorInitializer($associations);
        $assignments = $this->getAssociatedHydratorAssignments($table, $associations);

        $uses = implode("\n", array_unique($uses));
        $properties = implode("\n", array_unique($properties));
        $constructorInitializer = implode("\n", array_unique($constructorInitializer));
        $constructorParameters = implode(", ", array_unique($constructorParameters));
        $assignments = implode("\n", $assignments);

        if (!empty($constructorParameters)) {
            $constructorParameters  = ',' . $constructorParameters;
        }

        $template = str_replace(
            ['#classUc#', '#classLc#', '#assignments#', '#uses#', '#properties#', '#constructorParameters#', '#constructorInitializer#'],
            [ucfirst($class), lcfirst($class), $assignments, $uses, $properties, $constructorParameters, $constructorInitializer],
            file_get_contents(__DIR__.'/generator_templates/detail_hydrator.txt')
        );

        $file = $this->directory.'/'.ucfirst($class).'/Reader/'.ucfirst($class).'DetailHydrator.php';
        file_put_contents($file, $template);

        return $this->createDetailHydratorServiceXml($table, $associations);
    }

    private function createHydrator(string $table, array $config)
    {
        $class = $this->snakeCaseToCamelCase($table);

        $assignments = $this->createColumnAssignments($table, $config);
        $associations = $this->getAssociationsForBasicQuery($table, $config);

        $uses = $this->getAssociatedHydratorUsages($associations);
        $properties = $this->getAssociatedHydratorProperties($associations);
        $constructorParameters = $this->getAssociatedHydratorConstructorParameters($associations);
        $constructorInitializer = $this->getAssociatedHydratorConstructorInitializer($associations);
        $assignments = array_merge($assignments, $this->getAssociatedHydratorAssignments($table, $associations));

        $uses = implode("\n", array_unique($uses));
        $properties = implode("\n", array_unique($properties));
        $constructorInitializer = implode("\n", array_unique($constructorInitializer));
        $constructorParameters = implode(", ", array_unique($constructorParameters));
        $assignments = implode("\n", $assignments);

        $template = str_replace(
            ['#classUc#', '#classLc#', '#assignments#', '#uses#', '#properties#', '#constructorParameters#', '#constructorInitializer#'],
            [ucfirst($class), lcfirst($class), $assignments, $uses, $properties, $constructorParameters, $constructorInitializer],
            file_get_contents(__DIR__.'/generator_templates/hydrator.txt')
        );

        $file = $this->directory.'/'.ucfirst($class).'/Reader/'.ucfirst($class).'BasicHydrator.php';
        file_put_contents($file, $template);

        return $this->createHydratorServiceXml($table, $associations);
    }

    private function createReader(string $table, array $config, string $suffix)
    {
        $class = $this->snakeCaseToCamelCase($table);

        $template = str_replace(
            ['#classUc#', '#classLc#', '#suffix#'],
            [ucfirst($class), lcfirst($class), ucfirst($suffix)],
            file_get_contents(__DIR__.'/generator_templates/reader.txt')
        );

        $file = $this->directory.'/'.ucfirst($class).'/Reader/'.ucfirst($class). ucfirst($suffix) . 'Reader.php';
        file_put_contents($file, $template);

        return $this->createReaderServiceXml($table, $suffix);
    }

    private function createDetailQuery(string $table, array $config)
    {
        $class = $this->snakeCaseToCamelCase($table);
        $associations = $this->getAssociationsForDetailQuery($table, $config);

        $joins = $this->buildQueryJoins($table, $associations);

        $uses = $this->getAssociatedBasicQueryUsages($associations);
        $uses = implode("\n", array_unique($uses));
        $joins = implode("\n", $joins);

        $template = str_replace(
            ['#classUc#', '#joins#', '#uses#'],
            [ucfirst($class),$joins, $uses],
            file_get_contents(__DIR__.'/generator_templates/detail_query.txt')
        );

        $file = $this->directory.'/'.ucfirst($class).'/Reader/Query/'.ucfirst($class).'DetailQuery.php';
        file_put_contents($file, $template);
    }

    private function getAssociatedBasicQueryUsages($associations)
    {
        $uses = [];
        foreach ($associations as $association) {
            //many to many is used for sub selects with group concat, no use required
            if ($this->isToMany($association['type'])) {
                continue;
            }
            $associationClass = $this->snakeCaseToCamelCase($association['table']);

            $uses[] = str_replace(
                ['#associationClassUc#'],
                [ucfirst($associationClass)],
                file_get_contents(__DIR__.'/generator_templates/basic_query_use_basic_query.txt')
            );
        }

        return $uses;
    }

    private function createQuery(string $table, array $config)
    {
        $class = $this->snakeCaseToCamelCase($table);

        $select = $this->getSelect($table, $config);
        $select = implode(",\n", $select);

        $associations = $this->getAssociationsForBasicQuery($table, $config);
        $joins = $this->buildQueryJoins($table, $associations);
        $joins = implode("\n", $joins);

        $uses = $this->getAssociatedBasicQueryUsages($associations);
        $uses = implode("\n", array_unique($uses));

        $template = str_replace(
            ['#classUc#', '#classLc#', '#table#', '#fields#', '#joins#', '#uses#'],
            [ucfirst($class), lcfirst($class), $table, $select, $joins, $uses],
            file_get_contents(__DIR__.'/generator_templates/query.txt')
        );

        $file = $this->directory.'/'.ucfirst($class).'/Reader/Query/'.ucfirst($class).'BasicQuery.php';
        file_put_contents($file, $template);
    }

    private function createDetailLoader($table, $config, $associations, $template)
    {
        $class = $this->snakeCaseToCamelCase($table);

        $fetches = $this->getAssociatedLoaderFetches($table, $associations);
        $assignments = $this->getAssociatedLoaderAssignments($table, $associations);
        $properties = $this->getAssociatedLoaderProperties($associations);
        $constructorParameters = $this->getAssociatedLoaderConstructorParameters($associations);
        $constructorInitializer = $this->getAssociatedLoaderConstructorParameterInitializers($associations);
        $uses = $this->getAssociatedLoaderUsages($table, $associations);

        $template = file_get_contents($template);

        $uses = implode("\n", array_unique($uses));
        $fetches = implode("\n", array_unique($fetches));
        $assignments = implode("\n", $assignments);
        $properties = implode("\n", array_unique($properties));
        $constructorInitializer = implode("\n", array_unique($constructorInitializer));
        $constructorParameters = implode(",\n", array_unique($constructorParameters));

        if (!empty($properties)) {
            $constructorParameters = ",\n" . $constructorParameters;
        }

        $template = str_replace(
            ['#iteration#', '#classLc#', '#classUc#', '#uses#', '#properties#', '#constructorParameters#', '#constructorInitializer#', '#fetches#', '#assignments#'],
            ['', lcfirst($class), ucfirst($class), $uses, $properties, $constructorParameters, $constructorInitializer, $fetches, $assignments],
            $template
        );

        $file = $this->directory.'/'.ucfirst($class).'/Loader/'.ucfirst($class).'DetailLoader.php';
        file_put_contents($file, $template);
    }

    private function getAssociatedLoaderUsages($table, $associations)
    {
        $class = $this->snakeCaseToCamelCase($table);

        $uses = [];
        foreach ($associations as $association) {
            $associationClass = $this->snakeCaseToCamelCase($association['table']);
            switch ($association['type']) {
                case '1:N':
                    $uses[] = str_replace(
                        ['#classUc#', '#associationClassUc#'],
                        [ucfirst($class), ucfirst($associationClass)],
                        file_get_contents(__DIR__.'/generator_templates/basic_loader_use_searcher.txt')
                    );

                    break;

                case '1:1':
                case 'N:1':
                case 'N:N':
                    $uses[] = str_replace(
                        ['#classUc#'],
                        [ucfirst($associationClass)],
                        file_get_contents(__DIR__.'/generator_templates/use_basic_loader.txt')
                    );
                    break;
            }
        }

        return $uses;
    }

    private function getAssociatedLoaderProperties($associations)
    {
        $properties = [];

        foreach ($associations as $association) {
            $associationClass = $this->snakeCaseToCamelCase($association['table']);
            $associationClassPlural = $this->getPlural($associationClass);

            switch ($association['type']) {
                case '1:N':
                    $properties[] = str_replace(
                        ['#classUc#', '#classLc#', '#suffix#'],
                        [ucfirst($associationClass), lcfirst($associationClass), 'Searcher'],
                        file_get_contents(__DIR__.'/generator_templates/association_property.txt')
                    );
                    break;
                case '1:1':
                case 'N:1':
                case 'N:N':
                    $properties[] = str_replace(
                        ['#classUc#', '#classLc#', '#suffix#'],
                        [ucfirst($associationClass), lcfirst($associationClass), 'BasicLoader'],
                        file_get_contents(__DIR__.'/generator_templates/association_property.txt')
                    );
                    break;
            }
        }

        return $properties;
    }

    private function getAssociatedLoaderConstructorParameters($associations)
    {
        $constructorParameters = [];
        foreach ($associations as $association) {
            $associationClass = $this->snakeCaseToCamelCase($association['table']);

            switch ($association['type']) {
                case '1:N':
                    $constructorParameters[] = str_replace(
                        ['#classUc#', '#classLc#', '#suffix#'],
                        [ucfirst($associationClass), lcfirst($associationClass), 'Searcher'],
                        file_get_contents(__DIR__.'/generator_templates/constructor_parameter.txt')
                    );
                    break;
                case '1:1':
                case 'N:1':
                case 'N:N':
                    $constructorParameters[] = str_replace(
                        ['#classUc#', '#classLc#', '#suffix#'],
                        [ucfirst($associationClass), lcfirst($associationClass), 'BasicLoader'],
                        file_get_contents(__DIR__.'/generator_templates/constructor_parameter.txt')
                    );
                    break;
            }


        }
        return $constructorParameters;
    }

    private function getAssociatedLoaderConstructorParameterInitializers($associations)
    {
        $constructorInitializer = [];
        foreach ($associations as $association) {
            $associationClass = $this->snakeCaseToCamelCase($association['table']);
            $associationClassPlural = $this->getPlural($associationClass);

            switch ($association['type']) {
                case '1:N':
                    $constructorInitializer[] = str_replace(
                        ['#classLc#', '#suffix#'],
                        [lcfirst($associationClass), 'Searcher'],
                        file_get_contents(__DIR__.'/generator_templates/constructor_parameter_initializer.txt')
                    );
                    break;
                case '1:1':
                case 'N:1':
                case 'N:N':
                    $constructorInitializer[] = str_replace(
                        ['#classLc#', '#suffix#'],
                        [lcfirst($associationClass), 'BasicLoader'],
                        file_get_contents(__DIR__.'/generator_templates/constructor_parameter_initializer.txt')
                    );
                    break;
            }

        }
        return $constructorInitializer;
    }

    private function getAssociatedLoaderFetches($table, $associations)
    {
        $fetches = [];
        $class = $this->snakeCaseToCamelCase($table);

        foreach ($associations as $association) {
            $associationClass = $this->snakeCaseToCamelCase($association['table']);
            $associationClassPlural = $this->getPlural($associationClass);

            switch ($association['type']) {
                case '1:N':
                    $fetches[] = str_replace(
                        ['#classLc#', '#classUc#', '#plural#', '#associationClassLc#', '#associationClassUc#'],
                        [lcfirst($associationClass), ucfirst($class), lcfirst($associationClassPlural), lcfirst($associationClass), ucfirst($associationClass)],
                        file_get_contents(__DIR__.'/generator_templates/basic_loader_one_to_many_fetches.txt')
                    );
                    break;

                case '1:1':
                case 'N:1':
                case 'N:N':
                    $foreignKey = $this->getForeignKeyColumn($table, $association);

                    $fetches[] = str_replace(
                        ['#classLc#', '#classUc#', '#plural#'],
                        [lcfirst($associationClass), ucfirst($foreignKey), lcfirst($associationClassPlural)],
                        file_get_contents(__DIR__.'/generator_templates/basic_loader_many_to_many_fetches.txt')
                    );
                    break;
            }
        }
        return $fetches;
    }

    private function getAssociatedLoaderAssignments($table, $associations)
    {
        $class = $this->snakeCaseToCamelCase($table);

        $assignments = [];
        foreach ($associations as $association) {
            $associationClass = $this->snakeCaseToCamelCase($association['table']);
            $associationClassPlural = $this->getPlural($associationClass);
            $propertyName = $this->getAssociationPropertyName($association);
            $propertyNamePlural = $this->getPlural($propertyName);

            switch ($association['type']) {
                case '1:N':
                    $assignments[] = str_replace(
                        ['#classLc#', '#classUc#', '#propertyNameUc#', '#associationPluralUc#', '#associationPluralLc#'],
                        [lcfirst($class), ucfirst($class), ucfirst($propertyNamePlural), ucfirst($associationClassPlural), lcfirst($associationClassPlural), ucfirst($associationClass)],
                        file_get_contents(__DIR__.'/generator_templates/one_to_many_loader_assignment.txt')
                    );

                    break;
                case 'N:N':
                    $assignments[] = str_replace(
                        ['#classLc#', '#associationPluralUc#', '#associationPluralLc#', '#associationClassUc#'],
                        [lcfirst($class), ucfirst($associationClassPlural), lcfirst($associationClassPlural), ucfirst($associationClass)],
                        file_get_contents(__DIR__.'/generator_templates/many_to_many_loader_assignment.txt')
                    );
                    break;
                case '1:1':
                case 'N:1':
                    $foreignKey = $this->getForeignKeyColumn($table, $association);

                    $assignments[] = str_replace(
                        ['#classLc#', '#plural#', '#foreignKeyColumnUc#', '#propertyNameUc#'],
                        [lcfirst($class), lcfirst($associationClassPlural), ucfirst($foreignKey), ucfirst($propertyName)],
                        file_get_contents(__DIR__.'/generator_templates/basic_load_assignment.txt')
                    );
                    break;
            }
        }
        return $assignments;
    }

    public function createLoader(string $table, array $config)
    {
        $class = $this->snakeCaseToCamelCase($table);

        $associations = $this->getAssociationsForBasicLoader($table, $config);
        $fetches = $this->getAssociatedLoaderFetches($table, $associations);
        $assignments = $this->getAssociatedLoaderAssignments($table, $associations);
        $properties = $this->getAssociatedLoaderProperties($associations);
        $constructorParameters = $this->getAssociatedLoaderConstructorParameters($associations);
        $constructorInitializer = $this->getAssociatedLoaderConstructorParameterInitializers($associations);
        $uses = $this->getAssociatedLoaderUsages($table, $associations);

        $template = file_get_contents(__DIR__.'/generator_templates/loader.txt');

        $uses = implode("\n", array_unique($uses));
        $fetches = implode("\n", array_unique($fetches));
        $assignments = implode("\n", array_unique($assignments));
        $properties = implode("\n", array_unique($properties));
        $constructorInitializer = implode("\n", array_unique($constructorInitializer));
        $constructorParameters = implode(",\n", array_unique($constructorParameters));

        if (!empty($properties)) {
            $template = str_replace(
                ['#iteration#'],
                [file_get_contents(__DIR__.'/generator_templates/basic_loader_iteration.txt')],
                $template
            );
            $constructorParameters = ",\n" . $constructorParameters;
        }

        $template = str_replace(
            ['#iteration#', '#classLc#', '#classUc#', '#uses#', '#properties#', '#constructorParameters#', '#constructorInitializer#', '#fetches#', '#assignments#'],
            ['', lcfirst($class), ucfirst($class), $uses, $properties, $constructorParameters, $constructorInitializer, $fetches, $assignments],
            $template
        );

        $file = $this->directory.'/'.ucfirst($class).'/Loader/'.ucfirst($class).'BasicLoader.php';
        file_put_contents($file, $template);

        return $this->createLoaderServiceXml($table, $associations);
    }

    public function createSearcher(string $table, array $config)
    {
        $class = $this->snakeCaseToCamelCase($table);

        if ($this->getAssociationsForBasicLoader($table, $config)) {
            $template = str_replace(
                ['#classUc#', '#classLc#', '#table#'],
                [ucfirst($class), lcfirst($class), $table],
                file_get_contents(__DIR__.'/generator_templates/searcher_with_basic_loader.txt')
            );

            $file = $this->directory.'/'.ucfirst($class).'/Searcher/'.ucfirst($class).'Searcher.php';
            file_put_contents($file, $template);
            return $this->createSearcherWithBasicLoaderXml($table);
        }

        $template = str_replace(
            ['#classUc#'],
            [ucfirst($class)],
            file_get_contents(__DIR__.'/generator_templates/searcher_with_basic_query.txt')
        );

        $file = $this->directory.'/'.ucfirst($class).'/Searcher/'.ucfirst($class).'Searcher.php';
        file_put_contents($file, $template);
        return $this->createSearcherWithBasicQueryXml($table);
    }

    public function createSearchResult(string $table, array $config)
    {
        $class = $this->snakeCaseToCamelCase($table);

        $template = str_replace(
            ['#classUc#'],
            [ucfirst($class)],
            file_get_contents(__DIR__.'/generator_templates/search_result.txt')
        );

        $file = $this->directory.'/'.ucfirst($class).'/Searcher/'.ucfirst($class).'SearchResult.php';
        file_put_contents($file, $template);
    }

    public function createRepository(string $table, array $config)
    {
        $class = $this->snakeCaseToCamelCase($table);

        $uses = [];
        $parameters = [];
        $properties = [];
        $functions = [];
        $initializer = [];

        if ($config['create_detail']) {
            $uses[] = str_replace(['#classUc#'], [ucfirst($class)], file_get_contents(__DIR__.'/generator_templates/repository_use_detail.txt'));
            $properties[] = str_replace(['#type#', '#name#'], [ucfirst($class) . 'DetailLoader', 'detailLoader'], file_get_contents(__DIR__.'/generator_templates/property.txt'));
            $parameters[] = str_replace(['#classUc#'], [ucfirst($class)], file_get_contents(__DIR__.'/generator_templates/detail_loader_parameter.txt'));
            $initializer[] = file_get_contents(__DIR__.'/generator_templates/detail_loader_initializer.txt');
            $functions[] = str_replace(['#classUc#'], [ucfirst($class)], file_get_contents(__DIR__.'/generator_templates/repository_detail_function.txt'));
        }

        $uses = implode("\n", $uses);
        $parameters = implode("\n", $parameters);
        $functions = implode("\n", $functions);
        $initializer = implode("\n", $initializer);
        $properties = implode("\n", $properties);

        if (!empty($parameters)) {
            $parameters = ",\n" . $parameters;
        }
        $template = str_replace(
            ['#classUc#', '#uses#','#parameters#','#functions#','#initializer#', '#properties#'],
            [ucfirst($class), $uses, $parameters, $functions, $initializer, $properties],
            file_get_contents(__DIR__.'/generator_templates/repository.txt')
        );

        $file = $this->directory.'/'.ucfirst($class).'/'.ucfirst($class).'Repository.php';
        file_put_contents($file, $template);
                                       
        return $this->createRepositoryServiceXml($table, $config);
    }

    public function createWriter(string $table, array $config)
    {
        $class = $this->snakeCaseToCamelCase($table);

        $template = str_replace(
            ['#classUc#'],
            [ucfirst($class)],
            file_get_contents(__DIR__ . '/generator_templates/writer.txt')
        );

        $file = $this->directory.'/'.ucfirst($class).'/Writer/' . ucfirst($class)  . 'Writer.php';
        file_put_contents($file, $template);
        return $this->createWriterServiceXml($table);
    }

    public function createServicesXml(string $table, array $config, array $services, $handlerServices)
    {
        $class = $this->snakeCaseToCamelCase($table);

        $template = str_replace(
            ['#services#'],
            [implode($services)],
            file_get_contents(__DIR__ . '/generator_templates/services.xml.txt')
        );

        $file = $this->directory.'/'.ucfirst($class).'/DependencyInjection/services.xml';
        file_put_contents($file, $template);
    }

    private function getType(Column $column): string
    {
        switch ($column->getType()->getName()) {
            case Type::TARRAY:
            case Type::SIMPLE_ARRAY:
            case Type::JSON_ARRAY:
            case Type::OBJECT:
                return 'array';

            case Type::BOOLEAN:
                return 'bool';

            case Type::STRING:
            case Type::FLOAT:
                return $column->getType()->getName();

            case Type::BLOB:
            case Type::TEXT:
                return 'string';

            case Type::INTEGER:
            case Type::SMALLINT:
            case Type::BIGINT:
            case Type::BINARY:
            case Type::GUID:
                return 'int';

            case Type::DATETIME:
            case Type::DATETIMETZ:
            case Type::DATE:
            case Type::TIME:
                return '\DateTime';

            case Type::DECIMAL:
                return 'float';

            default:
                return 'string';
        }
    }

    /**
     * @param Column[] $columns
     * @return array
     */
    private function createStructProperties($table, array $columns, array $config): array
    {
        $properties = [];
        foreach ($columns as $column) {

            $propertyName = $this->getPropertyName($table, $column->getName());
            $type = $this->getType($column);

            if (!$column->getNotnull()) {
                $type .= '|null';
            }

            if (array_key_exists($column->getName(), $config['columns'])) {
                $type = $config['columns'][$column->getName()]['type'];
            }

            $properties[] = str_replace(
                ['#type#', '#name#'],
                [$type, lcfirst($propertyName)],
                file_get_contents(__DIR__.'/generator_templates/property.txt')
            );
        }

        return $properties;
    }

    /**
     * @param array $columns
     * @param array $config
     * @return array
     */
    private function createGetterAndSetters(string $table, array $columns, array $config)
    {
        $functions = [];
        foreach ($columns as $column) {

            $propertyName = $this->getPropertyName($table, $column->getName());

            $type = $this->getType($column);

            if (!$column->getNotnull()) {
                $type = '?' . $type;
            }
            if (array_key_exists($column->getName(), $config['columns'])) {
                $type = $config['columns'][$column->getName()]['type'];
            }

            $functions[] = str_replace(
                ['#nameUc#', '#nameLc#', '#type#'],
                [ucfirst($propertyName), lcfirst($propertyName), $type],
                file_get_contents(__DIR__ . '/generator_templates/struct_property_getter_setter.txt')
            );
        }

        return $functions;
    }

    private function getPlural(string $name)
    {
        $lastChar = substr($name, strlen($name) - 1, 1);

        switch (true) {
            case ($name === 'CustomerAddress'):
                return 'CustomerAddresses';
            case ($name == 'Holiday'):
                return 'Holidays';
            case ($lastChar === 'y'):
                return substr($name, 0, strlen($name) - 1) . 'ies';
            default:
                return $name . 's';
        }
    }

    private function createCollectiveUuidGetters(string $table)
    {
        $columns = array_filter(
            $this->getColumns($table),
            function(Column $column) {
                return strpos($column->getName(), '_uuid') !== false;
            }
        );

        $class = $this->snakeCaseToCamelCase($table);

        $getters = [];
        /** @var Column $column */
        foreach ($columns as $column) {
            $columnName = $this->getPropertyName($table, $column->getName());

            $getters[] = str_replace(
                ['#classUc#', '#classLc#', '#propertyUc#'],
                [ucfirst($class), lcfirst($class), ucfirst($columnName)],
                file_get_contents(__DIR__ . '/generator_templates/collection_uuid_getter.txt')
            );

            $getters[] = str_replace(
                ['#classUc#', '#classLc#', '#nameUc#'],
                [ucfirst($class), lcfirst($class), ucfirst($columnName)],
                file_get_contents(__DIR__ . '/generator_templates/collection_uuid_filter.txt')
            );
        }

        return array_unique($getters);
    }


    private function createColumnAssignments(string $table, array $config)
    {
        $columns = $this->getColumns($table);
        $class = $this->snakeCaseToCamelCase($table);

        $assignments = [];
        foreach ($columns as $column) {
            $propertyName = $this->getPropertyName($table, $column->getName());
            $columnName = $column->getName();

            $type = $this->getType($column);
            if (array_key_exists($column->getName(), $config['columns'])) {
                $type = $config['columns'][$column->getName()]['type'];
            }

            switch ($type) {
                case '\DateTime':
                    $template = __DIR__ . '/generator_templates/hydrator_cast_date.txt';
                    break;
                case 'array':
                    $template = __DIR__ . '/generator_templates/hydrator_cast_array.txt';
                    break;
                default:
                    $template = __DIR__ . '/generator_templates/hydrator_cast.txt';
            }

            $casted = str_replace(
                ['#type#', '#classLc#', '#column#'],
                [$type, lcfirst($class), $columnName],
                file_get_contents($template)
            );

            $template = __DIR__ . '/generator_templates/hydrator_assignment.txt';
            if (!$column->getNotnull() && $type !== 'array') {
                $template = __DIR__ . '/generator_templates/hydrator_assignment_nullable.txt';
            }

            $assignments[] = str_replace(
                ['#classLc#', '#propertyUc#', '#column#', '#casted#'],
                [lcfirst($class), ucfirst($propertyName), $columnName, $casted],
                file_get_contents($template)
            );
        }


        return array_unique($assignments);
    }

    /**
     * @param string $table
     * @return Column[]
     */
    private function getColumns(string $table): array
    {
        return $this->connection->getSchemaManager()->listTableColumns($table);
    }

    private function getByParentGetter($table, $config)
    {
        if ($config['get_list_by_parent']) {

        }

        if ($config['get_by_parent']) {

        }
        return '';
    }

    private function getSelect($table, $config): array
    {
        $columns = $this->getColumns($table);
        $class = $this->snakeCaseToCamelCase($table);

        $selects = [];
        $selects[] = str_replace(
            ['#classLc#'],
            [lcfirst($class)],
            file_get_contents(__DIR__ . '/generator_templates/column_select_array_key.txt')
        );

        foreach ($columns as $column) {
            $selects[] = str_replace(
                ['#classLc#', '#column#'],
                [lcfirst($class), $column->getName()],
                file_get_contents(__DIR__ . '/generator_templates/column_select.txt')
            );
        }
        return $selects;
    }

    private function createBasicQueryManyToOneJoins(string $table, array $associations)
    {
        $class = $this->snakeCaseToCamelCase($table);

        $joins = [];
        foreach ($associations as $association) {
            $associationTable = $association['table'];
            $associationClass = $this->snakeCaseToCamelCase($association['table']);

            $joins[] = str_replace(
                ['#classLc#', '#associationTable#', '#associationClassLc#', '#associationClassUc#'],
                [lcfirst($class), $associationTable, lcfirst($associationClass), ucfirst($associationClass)],
                file_get_contents(__DIR__ . '/generator_templates/basic_many_to_one_join.txt')
            );
        }

        return array_unique($joins);
    }


    private function getAssociationsForDetailStruct($table, $config)
    {
        return array_filter($config['associations'], function($association) {
            return $association['in_basic'] === false;
        });
    }

    private function getAssociationsForBasicStruct($table, $config)
    {
        return array_filter($config['associations'], function($association) {
            return $association['in_basic'] === true;
        });
    }

    private function getAssociationsForDetailQuery($table, $config)
    {
        return array_filter($config['associations'], function($association) {
            return $association['in_basic'] === false
                && ($association['load_by_association_loader'] === false || $association['type'] === 'N:N');
        });
    }

    private function getAssociationsForBasicQuery($table, $config)
    {
        return array_filter($config['associations'], function($association) {
            return $association['in_basic'] === true
                && ($association['load_by_association_loader'] === false || $association['type'] === 'N:N');
        });
    }

    private function getAssociationsForBasicLoader($table, $config)
    {
        return array_filter($config['associations'], function($association) {
            return $association['in_basic'] === true
                && ($association['load_by_association_loader'] === true || $this->isToMany($association['type']));
        });
    }

    private function getAssociationsForDetailLoader($table, $config)
    {
        return array_filter($config['associations'], function($association) {
            return $association['in_basic'] === false
                && ($association['load_by_association_loader'] === true || $this->isToMany($association['type']));
        });
    }


    private function filterAssociationType($associations, $types)
    {
        return array_filter($associations, function($assoc) use ($types) {
            return in_array($assoc['type'], $types, true);
        });
    }

    private function createStructAssociationFunctions($table, $config, $associations)
    {
        $properties = [];

        foreach ($associations as $association) {
            $associationClass = $this->snakeCaseToCamelCase($association['table']);
            $propertyName = $this->getAssociationPropertyName($association);

            switch (true) {
                case ($this->isToOne($association['type'])):
                    $properties[] = str_replace(
                        ['#propertyNameUc#', '#propertyNameLc#', '#associationClassUc#'],
                        [ucfirst($propertyName), lcfirst($propertyName), ucfirst($associationClass)],
                        file_get_contents(__DIR__ . '/generator_templates/to_one_association_property_getter_and_setter.txt')
                    );
                    break;

                case ($this->isToMany($association['type'])):
                    $plural = $this->getPlural($propertyName);
                    $properties[] = str_replace(
                        ['#classLc#', '#classUc#'],
                        [lcfirst($propertyName), ucfirst($propertyName)],
                        file_get_contents(__DIR__ . '/generator_templates/struct_many_to_many_uuid_functions.txt')
                    );
                    $properties[] = str_replace(
                        ['#associationClassUc#', '#plural#', '#pluralUc#'],
                        [ucfirst($associationClass), lcfirst($plural), ucfirst($plural)],
                        file_get_contents(__DIR__ . '/generator_templates/to_many_association_property_getter_and_setter.txt')
                    );
                    break;
            }
        }

        return array_unique($properties);
    }

    private function createStructAssociationProperties($table, $config, $associations)
    {
        $properties = [];

        foreach ($associations as $association) {
            $associationClass = $this->snakeCaseToCamelCase($association['table']);
            $propertyName = $this->getAssociationPropertyName($association);

            switch (true) {
                case ($this->isToOne($association['type'])):

                    $properties[] = str_replace(
                        ['#associationClassUc#', '#associationClassLc#'],
                        [ucfirst($associationClass), $propertyName],
                        file_get_contents(__DIR__ . '/generator_templates/to_one_association_property.txt')
                    );
                    break;

                case ($this->isToMany($association['type'])):
                    $plural = $this->getPlural($propertyName);
                    $properties[] = str_replace(
                        ['#classLc#'],
                        [lcfirst($propertyName)],
                        file_get_contents(__DIR__ . '/generator_templates/to_many_uuids_property.txt')
                    );
                    $properties[] = str_replace(
                        ['#associationClassUc#', '#plural#'],
                        [ucfirst($associationClass), lcfirst($plural)],
                        file_get_contents(__DIR__ . '/generator_templates/to_many_association_property.txt')
                    );
                    break;
            }
        }

        return array_unique($properties);
    }

    private function isToOne($type)
    {
        return in_array($type, self::TO_ONE);
    }

    private function isToMany($type)
    {
        return in_array($type, self::TO_MANY);
    }

    private function createCollectionAssociationGetters($table, $associations, $suffix = 'Basic')
    {
        $getters = [];
        $class = $this->snakeCaseToCamelCase($table);

        foreach ($associations as $association) {
            $associationClass = $this->snakeCaseToCamelCase($association['table']);
            $property = $this->getAssociationPropertyName($association);

            $plural = $this->getPlural($property);

            if ($this->isToOne($association['type'])) {
                $getters[] = str_replace(
                    ['#pluralUc#', '#associationClassUc#', '#classUc#', '#classLc#', '#suffix#', '#propertyUc#'],
                    [ucfirst($plural), ucfirst($associationClass), ucfirst($class), lcfirst($class), ucfirst($suffix), ucfirst($property)],
                    file_get_contents(__DIR__ . '/generator_templates/collective_to_one_association_getter.txt')
                );

                continue;
            }
            $getters[] =  str_replace(
                ['#classUc#'],
                [ucfirst($property)],
                file_get_contents(__DIR__ . '/generator_templates/collective_many_to_many_uuid_getter.txt')
            );
            $getters[] = str_replace(
                ['#pluralUc#', '#associationClassUc#'],
                [ucfirst($plural), ucfirst($associationClass)],
                file_get_contents(__DIR__ . '/generator_templates/collective_to_many_association_getter.txt')
            );
        }

        return $getters;
    }

    /**
     * @param array $association
     * @return string
     */
    private function createAssociationStructUsage(array $association): string
    {
        $associationClass = $this->snakeCaseToCamelCase($association['table']);

        if ($this->isToMany($association['type'])) {
            return str_replace(
                ['#associationClassUc#', '#suffix#'],
                [ucfirst($associationClass), 'BasicCollection'],
                file_get_contents(__DIR__.'/generator_templates/use_association_basic_struct.txt')
            );
        }

        return str_replace(
            ['#associationClassUc#', '#suffix#'],
            [ucfirst($associationClass), 'BasicStruct'],
            file_get_contents(__DIR__.'/generator_templates/use_association_basic_struct.txt')
        );
    }

    private function createStructAssociationInitializer($associations)
    {
        $initializer = [];
        foreach ($associations as $association) {
            if (!$this->isToMany($association['type'])) {
                continue;
            }
            $associationClass = $this->snakeCaseToCamelCase($association['table']);
            $propertyName = $this->getAssociationPropertyName($association);
            $plural = $this->getPlural($propertyName);

            $initializer[] = str_replace(
                ['#plural#', '#classUc#'],
                [lcfirst($plural), ucfirst($associationClass)],
                file_get_contents(__DIR__.'/generator_templates/to_many_association_constructor_initializer.txt')
            );
        }
        return $initializer;
    }


    private function getAssociatedHydratorUsages(array $associations)
    {
        $uses = [];
        foreach ($associations as $association) {
            $associationClass = $this->snakeCaseToCamelCase($association['table']);
            if ($this->isToMany($association['type'])) {
                continue;
            }

            $uses[] = str_replace(
                ['#classUc#'],
                [ucfirst($associationClass)],
                file_get_contents(__DIR__.'/generator_templates/use_hydrator.txt')
            );
        }
        return $uses;
    }

    private function getAssociatedHydratorProperties(array $associations)
    {
        $properties = [];

        foreach ($associations as $association) {
            $associationClass = $this->snakeCaseToCamelCase($association['table']);
            if ($this->isToMany($association['type'])) {
                continue;
            }
            $properties[] = str_replace(
                ['#classUc#', '#classLc#', '#suffix#'],
                [ucfirst($associationClass), lcfirst($associationClass), 'BasicHydrator'],
                file_get_contents(__DIR__.'/generator_templates/association_property.txt')
            );
        }
        return $properties;
    }

    private function getAssociatedHydratorConstructorParameters(array $associations)
    {
        $constructorParameters = [];

        foreach ($associations as $association) {
            $associationClass = $this->snakeCaseToCamelCase($association['table']);
            if ($this->isToMany($association['type'])) {
                continue;
            }
            $constructorParameters[] = str_replace(
                ['#classUc#', '#classLc#', '#suffix#'],
                [ucfirst($associationClass), lcfirst($associationClass), 'BasicHydrator'],
                file_get_contents(__DIR__.'/generator_templates/constructor_parameter.txt')
            );
        }
        return $constructorParameters;
    }

    private function getAssociatedHydratorConstructorInitializer(array $associations)
    {
        $constructorInitializer = [];

        foreach ($associations as $association) {
            $associationClass = $this->snakeCaseToCamelCase($association['table']);
            if ($this->isToMany($association['type'])) {
                continue;
            }
            $constructorInitializer[] = str_replace(
                ['#classLc#', '#suffix#'],
                [lcfirst($associationClass), 'BasicHydrator'],
                file_get_contents(__DIR__.'/generator_templates/constructor_parameter_initializer.txt')
            );
        }
        return $constructorInitializer;
    }

    private function getAssociatedHydratorAssignments($table, array $associations)
    {
        $class = $this->snakeCaseToCamelCase($table);

        $assignments = [];

        foreach ($associations as $association) {
            $associationClass = $this->snakeCaseToCamelCase($association['table']);
            if ($this->isToMany($association['type'])) {
                $cast = str_replace(
                    ['#classLc#', '#associationClassUc#', '#associationClassLc#'],
                    [lcfirst($class), ucfirst($associationClass), lcfirst($associationClass)],
                    file_get_contents(__DIR__.'/generator_templates/hydrator_cast_array.txt')
                );

                $assignments[] = str_replace(
                    ['#classLc#', '#propertyUc#', '#casted#', '#column#'],
                    [lcfirst($class), ucfirst($associationClass) . 'Uuids', $cast, $association['table'] . '_uuids'],
                    file_get_contents(__DIR__.'/generator_templates/hydrator_assignment.txt')
                );
                continue;
            }
            $propertyName = $this->getAssociationPropertyName($association);

            $assignments[] = str_replace(
                ['#classLc#', '#associationClassUc#', '#associationClassLc#'],
                [lcfirst($class), ucfirst($propertyName), lcfirst($associationClass)],
                file_get_contents(__DIR__.'/generator_templates/hydrator_assign_association.txt')
            );
        }
        return $assignments;
    }

    private function createHydratorServiceXml(string $table, array $associations)
    {
        $class = $this->snakeCaseToCamelCase($table);
        $arguments = [];
        foreach ($associations as $association) {
            $arguments[] = str_replace(
                ['#table#'],
                [$association['table']],
                file_get_contents(__DIR__.'/generator_templates/hydrator_parameter.xml.txt')
            );
        }

        $arguments = implode("\n", array_unique($arguments));

        return str_replace(
            ['#table#', '#classUc#', '#arguments#'],
            [$table, ucfirst($class), $arguments],
            file_get_contents(__DIR__.'/generator_templates/hydrator.xml.txt')
        );
    }

    private function createReaderServiceXml(string $table, $suffix)
    {
        $class = $this->snakeCaseToCamelCase($table);

        return str_replace(
            ['#table#', '#classUc#', '#suffixUc#', '#suffixLc#'],
            [$table, ucfirst($class), ucfirst($suffix), lcfirst($suffix)],
            file_get_contents(__DIR__.'/generator_templates/reader.xml.txt')
        );
    }

    private function createLoaderServiceXml(string $table, array $associations): string
    {
        $class = $this->snakeCaseToCamelCase($table);
        $arguments = [];
        foreach ($associations as $association) {
            $arguments[] = str_replace(
                ['#table#'],
                [$association['table']],
                file_get_contents(__DIR__.'/generator_templates/loader_parameter.xml.txt')
            );
        }
        $arguments = implode("\n", array_unique($arguments));

        return str_replace(
            ['#table#', '#classUc#', '#arguments#'],
            [$table, ucfirst($class), $arguments],
            file_get_contents(__DIR__.'/generator_templates/loader.xml.txt')
        );
    }

    private function createSearcherWithBasicQueryXml($table)
    {
        $class = $this->snakeCaseToCamelCase($table);
        return str_replace(
            ['#table#', '#classUc#'],
            [$table, ucfirst($class)],
            file_get_contents(__DIR__.'/generator_templates/searcher_with_basic_query.xml.txt')
        );
    }

    private function createSearcherWithBasicLoaderXml($table)
    {
        $class = $this->snakeCaseToCamelCase($table);
        return str_replace(
            ['#table#', '#classUc#'],
            [$table, ucfirst($class)],
            file_get_contents(__DIR__.'/generator_templates/searcher_with_basic_loader.xml.txt')
        );
    }

    private function createWriterServiceXml($table)
    {
        $class = $this->snakeCaseToCamelCase($table);
        return str_replace(
            ['#table#', '#classUc#'],
            [$table, ucfirst($class)],
            file_get_contents(__DIR__.'/generator_templates/writer.xml.txt')
        );
    }

    private function createRepositoryServiceXml($table, $config)
    {
        $class = $this->snakeCaseToCamelCase($table);
        $arguments = [];
        if ($config['create_detail']) {
            $arguments[] = str_replace(
                ['#table#'],
                [$table],
                file_get_contents(__DIR__.'/generator_templates/detail_loader_parameter.xml.txt')
            );
        }
        $arguments = implode("\n", array_unique($arguments));

        return str_replace(
            ['#table#', '#classUc#', '#arguments#'],
            [$table, ucfirst($class), $arguments],
            file_get_contents(__DIR__.'/generator_templates/repository.xml.txt')
        );
    }

    private function createDetailHydratorServiceXml($table, $associations)
    {
        $class = $this->snakeCaseToCamelCase($table);
        $arguments = [];
        foreach ($associations as $association) {
            $arguments[] = str_replace(
                ['#table#'],
                [$association['table']],
                file_get_contents(__DIR__.'/generator_templates/hydrator_parameter.xml.txt')
            );
        }

        $arguments = implode("\n", array_unique($arguments));

        return str_replace(
            ['#table#', '#classUc#', '#arguments#'],
            [$table, ucfirst($class), $arguments],
            file_get_contents(__DIR__.'/generator_templates/detail_hydrator.xml.txt')
        );
    }

    private function createDetailLoaderServiceXmlWithDetailReader($table, $associations)
    {
        $class = $this->snakeCaseToCamelCase($table);
        $arguments = [];
        foreach ($associations as $association) {
            if ($association['type'] === '1:N') {
                $arguments[] = str_replace(
                    ['#table#'],
                    [$association['table']],
                    file_get_contents(__DIR__.'/generator_templates/basic_searcher_parameter.xml.txt')
                );
                continue;
            }

            $arguments[] = str_replace(
                ['#table#'],
                [$association['table']],
                file_get_contents(__DIR__.'/generator_templates/basic_loader_parameter.xml.txt')
            );
        }

        $arguments = implode("\n", array_unique($arguments));

        return str_replace(
            ['#table#', '#classUc#', '#arguments#'],
            [$table, ucfirst($class), $arguments],
            file_get_contents(__DIR__.'/generator_templates/detail_loader_with_detail_reader.xml.txt')
        );
    }

    private function createDetailLoaderServiceXmlWithBasicLoader($table, $associations)
    {
        $class = $this->snakeCaseToCamelCase($table);
        $arguments = [];
        foreach ($associations as $association) {
            if ($association['type'] === '1:N') {
                $arguments[] = str_replace(
                    ['#table#'],
                    [$association['table']],
                    file_get_contents(__DIR__.'/generator_templates/basic_searcher_parameter.xml.txt')
                );
                continue;
            }

            $arguments[] = str_replace(
                ['#table#'],
                [$association['table']],
                file_get_contents(__DIR__.'/generator_templates/basic_loader_parameter.xml.txt')
            );
        }

        $arguments = implode("\n", array_unique($arguments));

        return str_replace(
            ['#table#', '#classUc#', '#arguments#'],
            [$table, ucfirst($class), $arguments],
            file_get_contents(__DIR__.'/generator_templates/detail_loader_with_basic_loader.xml.txt')
        );
    }

    private function buildQueryJoins($table, $associations)
    {
        $class = $this->snakeCaseToCamelCase($table);

        $joins = [];
        foreach ($associations as $association) {
            $associationTable = $association['table'];
            $associationClass = $this->snakeCaseToCamelCase($association['table']);

            switch ($association['type']) {
                case '1:1':
                    break;
                case '1:N':
                    break;
                case 'N:1':
                    if (!empty($association['condition'])) {
                        $joins[] = str_replace(
                            ['#classLc#', '#associationTable#', '#associationClassLc#', '#associationClassUc#', '#condition#'],
                            [lcfirst($class), $associationTable, lcfirst($associationClass), ucfirst($associationClass), $association['condition']],
                            file_get_contents(__DIR__ . '/generator_templates/basic_many_to_one_join_own_condition.txt')
                        );
                        break;
                    }

                    $joins[] = str_replace(
                        ['#classLc#', '#associationTable#', '#associationClassLc#', '#associationClassUc#'],
                        [lcfirst($class), $associationTable, lcfirst($associationClass), ucfirst($associationClass)],
                        file_get_contents(__DIR__ . '/generator_templates/basic_many_to_one_join.txt')
                    );
                    break;

                case 'N:N':
                    $joins[] = str_replace(
                        ['#classLc#', '#reference_table#', '#mapping_table#', '#table#'],
                        [lcfirst($class), $association['table'], $association['mapping'], $table],
                        file_get_contents(__DIR__.'/generator_templates/to_many_sub_select.txt')
                    );
                    break;
            }
        }

        return $joins;
    }

    private function createBundle($table)
    {
        $class = $this->snakeCaseToCamelCase($table);

        $template = str_replace(
            ['#classUc#'],
            [ucfirst($class)],
            file_get_contents(__DIR__.'/generator_templates/bundle.txt')
        );
        $file = $this->directory.'/'.ucfirst($class).'/'.ucfirst($class).'.php';

        file_put_contents($file, $template);
    }

    private function createDbalHandlers(string $table, array $config)
    {
        $columns = $this->getColumns($table);
        $class = $this->snakeCaseToCamelCase($table);

        foreach ($config['search'] as $criteriaPart) {
            $column = $columns[$criteriaPart['column']];

            $name = $this->getConditionName($criteriaPart);
            $plural = $this->getPlural($name);

            $uses = [];
            $implements = [];
            $supports = [];
            $sorting = '';
            $condition = '';

            $functions = [];

            if ($criteriaPart['sorting']) {
                $implements['handle'] = 'HandlerInterface';
                $supports[] = str_replace(['#searchNameUc#', '#suffix#'], [ucfirst($name), 'Sorting'], file_get_contents(__DIR__ . '/generator_templates/' . 'search_handler_supports.txt'));

                $sorting = str_replace(
                    ['#classLc#', '#classUc#', '#searchNameLc#',  '#searchNameUc#', '#column#', '#searchNamePluralUc#', '#searchNamePluralLc#'],
                    [lcfirst($class), lcfirst($class),  lcfirst($name),  ucfirst($name),  $column->getName(), ucfirst($plural), lcfirst($plural)],
                    file_get_contents($this->getSortingHandlerTemplate($column, $criteriaPart, 'dbal'))
                );

                $functions['handle'] = file_get_contents(__DIR__ . '/generator_templates/' . 'search_handler_handle.txt');
            }

            if ($criteriaPart['condition']) {
                $implements['handle'] = 'HandlerInterface';
                $supports[] = str_replace(['#searchNameUc#', '#suffix#'], [ucfirst($name), 'Condition'], file_get_contents(__DIR__ . '/generator_templates/' . 'search_handler_supports.txt'));

                $condition = str_replace(
                    ['#classLc#', '#classUc#', '#searchNameLc#',  '#searchNameUc#', '#column#', '#searchNamePluralUc#', '#searchNamePluralLc#'],
                    [lcfirst($class), lcfirst($class),  lcfirst($name),  ucfirst($name),  $column->getName(), ucfirst($plural), lcfirst($plural)],
                    file_get_contents($this->getConditionHandlerTemplate($column, $criteriaPart, 'dbal'))
                );

                $functions['handle'] = file_get_contents(__DIR__ . '/generator_templates/' . 'search_handler_handle.txt');
            }

            if ($criteriaPart['facet']) {
                $implements[] = 'AggregatorInterface';
                $supports[] = str_replace(['#searchNameUc#', '#suffix#'], [ucfirst($name), 'Facet'], file_get_contents(__DIR__ . '/generator_templates/' . 'search_handler_supports.txt'));

                $facetHandle = str_replace(
                    ['#classLc#', '#classUc#', '#searchNameLc#',  '#searchNameUc#', '#column#', '#searchNamePluralUc#', '#searchNamePluralLc#'],
                    [lcfirst($class), lcfirst($class),  lcfirst($name),  ucfirst($name),  $column->getName(), ucfirst($plural), lcfirst($plural)],
                    file_get_contents($this->getFacetHandlerTemplate($column, $criteriaPart, 'dbal'))
                );
                $uses[] = $this->getFacetResultUsage($column, $criteriaPart);

                $facetTemplate  = str_replace(
                    ['#aggregate_type#'],
                    [$facetHandle],
                    file_get_contents(__DIR__ . '/generator_templates/' . 'search_handler_aggregate.txt')
                );

                $functions['aggregate'] = $facetTemplate;
            }

            $supports = implode("\n || ", $supports);
            $functions = implode("\n", $functions);
            $uses = implode("\n", $uses);
            $implements = implode(',', $implements);

            $template = str_replace(
                ['#classUc#', '#searchNameUc#', '#supports#', '#functions#', '#implements#', '#uses#'],
                [ucfirst($class), ucfirst($name), $supports, $functions, $implements, $uses],
                file_get_contents(__DIR__ . '/generator_templates/' . 'search_handler.txt')
            );

            $template = str_replace(['#sorting#', '#condition#'], [$sorting, $condition], $template);

            $file = $this->directory.'/'.ucfirst($class).'/Searcher/Handler/' . ucfirst($name) . 'Handler.php';

            file_put_contents($file, $template);
        }

        return [];
    }

    private function createCriteriaParts(string $table, array $config)
    {
        $columns = $this->getColumns($table);

        foreach ($config['search'] as $criteriaPart) {
            $column = $columns[$criteriaPart['column']];
            $name = $this->getConditionName($criteriaPart);
            $plural = $this->getPlural($name);

            if ($criteriaPart['condition']) {
                $template = str_replace(
                    ['#searchNameLc#',  '#searchNameUc#', '#column#', '#searchNamePluralUc#', '#searchNamePluralLc#'],
                    [lcfirst($name),  ucfirst($name),  $column->getName(), ucfirst($plural), lcfirst($plural)],
                    file_get_contents($this->getConditionTemplate($column, $criteriaPart))
                );
                $file = $this->directory . '/Search/Condition/' . ucfirst($name) . 'Condition.php';
                file_put_contents($file, $template);
            }

            if ($criteriaPart['sorting']) {
                $template = str_replace(
                    ['#searchNameLc#',  '#searchNameUc#', '#column#', '#searchNamePluralUc#', '#searchNamePluralLc#'],
                    [lcfirst($name),  ucfirst($name),  $column->getName(), ucfirst($plural), lcfirst($plural)],
                    file_get_contents($this->getSortingTemplate($column, $criteriaPart))
                );
                $file = $this->directory . '/Search/Sorting/' . ucfirst($name) . 'Sorting.php';
                file_put_contents($file, $template);
            }
            if ($criteriaPart['facet']) {
                $template = str_replace(
                    ['#searchNameLc#',  '#searchNameUc#', '#column#', '#searchNamePluralUc#', '#searchNamePluralLc#'],
                    [lcfirst($name),  ucfirst($name),  $column->getName(), ucfirst($plural), lcfirst($plural)],
                    file_get_contents($this->getFacetTemplate($column, $criteriaPart))
                );
                $file = $this->directory . '/Search/Facet/' . ucfirst($name) . 'Facet.php';
                file_put_contents($file, $template);
            }
        }
    }

    private function getConditionHandlerTemplate(Column $column, array $criteriaPart, string $suffix = '')
    {
        switch (true) {
            case ($criteriaPart['type'] === 'string_array'):
                return __DIR__ . '/generator_templates/search_handler_condition_string_array_'. $suffix . '.txt';
            case ($criteriaPart['type'] === 'int_array'):
                return __DIR__ . '/generator_templates/search_handler_condition_int_array_'. $suffix . '.txt';

            case ($column->getType()->getName() === Type::BOOLEAN):
                return __DIR__ . '/generator_templates/search_handler_condition_boolean_'. $suffix . '.txt';

            default:
                throw new \RuntimeException(sprintf('Not supported search criteria data type %s for %s', $column->getType()->getName(), $column->getName()));
        }
    }

    private function getFacetHandlerTemplate(Column $column, array $criteriaPart, string $suffix = '')
    {
        switch (true) {
            case ($criteriaPart['type'] === 'string_array'):
                return __DIR__ . '/generator_templates/search_handler_aggregate_string_array_' . $suffix . '.txt';
            case ($criteriaPart['type'] === 'int_array'):
                return __DIR__ . '/generator_templates/search_handler_aggregate_int_array_' . $suffix . '.txt';

            case ($column->getType()->getName() === Type::BOOLEAN):
                return __DIR__ . '/generator_templates/search_handler_aggregate_boolean_'. $suffix . '.txt';

            default:
                throw new \RuntimeException(sprintf('Not supported search criteria data type %s for %s', $column->getType()->getName(), $column->getName()));
        }
    }

    private function getSortingHandlerTemplate(Column $column, array $criteriaPart, string $suffix = '')
    {
        switch (true) {
            default:
                return __DIR__ . '/generator_templates/search_handler_sorting_'. $suffix . '.txt';
        }
    }

    private function getConditionName(array $condition)
    {
        if (!empty($condition['className'])) {
            return $this->snakeCaseToCamelCase($condition['className']);
        }
        return $this->snakeCaseToCamelCase($condition['column']);
    }

    private function getFacetTemplate(Column $column, array $criteriaPart)
    {
        switch (true) {
            case ($criteriaPart['type'] === 'string_array'):
            case ($column->getType()->getName() === Type::BOOLEAN):
            default:
                return __DIR__ . '/generator_templates/search_facet.txt';
        }
    }

    private function getSortingTemplate(Column $column, array $criteriaPart)
    {
        switch (true) {

            default:
                return __DIR__ . '/generator_templates/search_sorting.txt';
        }
    }

    private function getConditionTemplate(Column $column, array $criteriaPart)
    {
        switch (true) {
            case ($criteriaPart['type'] === 'string_array'):
                return __DIR__ . '/generator_templates/search_condition_string_array.txt';

            case ($criteriaPart['type'] === 'int_array'):
                return __DIR__ . '/generator_templates/search_condition_int_array.txt';

            case ($column->getType()->getName() === Type::BOOLEAN):
                return __DIR__ . '/generator_templates/search_condition_boolean.txt';

            default:
                throw new \RuntimeException(sprintf('Not supported search criteria data type %s for %s', $column->getType()->getName(), $column->getName()));
        }
    }

    /**
     * @param $association
     * @return string
     */
    private function getAssociationPropertyName($association): string
    {
        if (!empty($association['property'])) {
            return lcfirst($association['property']);
        }
        return lcfirst(
            $this->snakeCaseToCamelCase($association['table'])
        );
    }

    /**
     * @param array $association
     * @return string
     */
    private function getForeignKeyColumn(string $table, array $association): string
    {
        if (!empty($association['foreignKeyColumn'])) {
            return $this->snakeCaseToCamelCase(
                str_replace('_uuid', '', $association['foreignKeyColumn'])
            );
        }

        $property = $this->getPropertyName($table, $association['table']);

        return $this->snakeCaseToCamelCase($property);
    }

    private function getFacetResultUsage(Column $column, array $criteriaPart)
    {
        switch (true) {
            case ($criteriaPart['type'] === 'string_array'):
            case ($criteriaPart['type'] === 'int_array'):
                return 'use Shopware\Search\FacetResult\ArrayFacetResult;';

            case ($column->getType()->getName() === Type::BOOLEAN):
                return 'use Shopware\Search\FacetResult\BooleanFacetResult;';

            default:
                return '';
        }
    }

    /**
     * @param string $table
     * @param string $columnName
     * @return string
     */
    private function getPropertyName(string $table, string $columnName): string
    {
        if (strpos($columnName, $table . '_') === 0) {
            $columnName = str_replace($table . '_', '', $columnName);
        }
        return $this->snakeCaseToCamelCase($columnName);
    }
}