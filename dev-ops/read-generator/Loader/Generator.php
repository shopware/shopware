<?php

namespace ReadGenerator\Loader;

use ReadGenerator\Util;

class Generator
{
    /**
     * @var string
     */
    private $directory;

    public function __construct($directory)
    {
        $this->directory = $directory;
    }

    public function getFiles($table)
    {
        $class = Util::snakeCaseToCamelCase($table);
        return [
            $this->directory.'/'.ucfirst($class).'/Loader/'.ucfirst($class).'BasicLoader.php',
            $this->directory.'/'.ucfirst($class).'/Loader/'.ucfirst($class).'DetailLoader.php',
        ];
    }


    public function generate(string $table, array $config)
    {
        $associations = Util::getAssociationsForBasicLoader($table, $config);

        list($uses, $properties, $constructor, $init, $fetches, $assignments, $class, $plural) = $this->getDependencies($table, $associations);

        $uses = implode("\n", array_unique($uses));
        $properties = implode("\n", array_unique($properties));
        $constructor = implode(",\n", array_unique($constructor));
        $init = implode("\n", array_unique($init));
        $fetches = implode("\n", array_unique($fetches));
        $assignments = implode("\n", array_unique($assignments));

        if (!empty($constructor)) {
            $constructor = ",\n" . $constructor;
        }

        $template = file_get_contents(__DIR__ . '/templates/loader.txt');
        $iteration = '';
        if (!empty($assignments)) {
            $iteration = '
#fetches#
/** @var #classUc#BasicStruct $#classLc# */
        foreach ($#plural#Collection as $#classLc#) {
#assignments#
        }
            ';
        }

        $template = str_replace('#iteration#', $iteration, $template);

        $template = str_replace(
            ['#classUc#', '#classLc#', '#plural#', '#structClass#', '#table#'],
            [ucfirst($class), lcfirst($class), lcfirst($plural), ucfirst($class) . 'Basic', $table],
            $template
        );

        $template = str_replace(
            ['#uses#','#properties#', '#constructor#', '#init#', '#fetches#', '#assignments#'],
            [$uses, $properties, $constructor, $init, $fetches, $assignments],
            $template
        );

        $file = $this->directory.'/'.ucfirst($class).'/Loader/'.ucfirst($class).'BasicLoader.php';

        file_put_contents($file, $template);
        
        return $this->createServiceXml($table, $config, $associations, 'basic');
    }

    public function generateDetail(string $table, array $config)
    {
        $associations = array_merge(
            Util::getAssociationsForBasicLoader($table, $config),
            Util::getAssociationsForDetailLoader($table, $config)
        );

        list($uses, $properties, $constructor, $init, $fetches, $assignments, $class, $plural) = $this->getDependencies($table, $associations);

        $uses = implode("\n", array_unique($uses));
        $properties = implode("\n", array_unique($properties));
        $constructor = implode(",\n", array_unique($constructor));
        $init = implode("\n", array_unique($init));
        $fetches = implode("\n", array_unique($fetches));
        $assignments = implode("\n", array_unique($assignments));

        if (!empty($constructor)) {
            $constructor = ",\n" . $constructor;
        }

        $template = file_get_contents(__DIR__ . '/templates/loader_detail.txt');
        $iteration = '';
        if (!empty($assignments)) {
            $iteration = '
#fetches#
        /** @var #classUc#DetailStruct $#classLc# */
        foreach ($#plural#Collection as $#classLc#) {
#assignments#
        }
            ';
        }

        $template = str_replace('#iteration#', $iteration, $template);

        $template = str_replace(
            ['#classUc#', '#classLc#', '#plural#', '#structClass#', '#table#'],
            [ucfirst($class), lcfirst($class), lcfirst($plural), ucfirst($class) . 'Detail', $table],
            $template
        );

        $template = str_replace(
            ['#uses#','#properties#', '#constructor#', '#init#', '#fetches#', '#assignments#'],
            [$uses, $properties, $constructor, $init, $fetches, $assignments],
            $template
        );

        $file = $this->directory.'/'.ucfirst($class).'/Loader/'.ucfirst($class).'DetailLoader.php';

        file_put_contents($file, $template);

        return $this->createServiceXml($table, $config, $associations, 'detail');
    }

    /**
     * @param string $table
     * @param $associations
     * @return array
     */
    private function getDependencies(string $table, $associations): array
    {
        $uses = [];
        $properties = [];
        $constructor = [];
        $init = [];
        $fetches = [];
        $assignments = [];

        $class = Util::snakeCaseToCamelCase($table);
        $plural = Util::getPlural($class);

        foreach ($associations as $association) {
            $property = Util::getAssociationPropertyName($association);
            $associationClass = Util::snakeCaseToCamelCase($association['table']);
            $associationPlural = Util::getPlural($property);

            switch ($association['type']) {
                case Util::ONE_TO_ONE:
                    if ($association['has_detail_loader']) {
                        $type = 'Detail';
                    } else {
                        $type = 'Basic';
                    }

                    if ($association['fetchTemplate'] !== null) {
                        $fetches[] = $association['fetchTemplate'];
                    } else {
                        $fetches[] = str_replace(
                            ['#associationPlural#', '#classLc#', '#plural#', '#propertyUc#', '#type#'],
                            [lcfirst($associationPlural), lcfirst($associationClass), lcfirst($plural), ucfirst($property), ucfirst($type)],
                            file_get_contents(__DIR__.'/templates/many_to_one_fetch.txt')
                        );
                    }
                    if ($association['assignTemplate'] !== null) {
                        $assignments[] = $association['assignTemplate'];
                    } else {
                        $assignments[] = str_replace(
                            ['#classLc#', '#propertyUc#', '#associationPlural#'],
                            [lcfirst($class), ucfirst($property), lcfirst($associationPlural)],
                            file_get_contents(__DIR__.'/templates/many_to_one_assignment.txt')
                        );
                    }

                    $constructor[] = str_replace(
                        ['#classUc#', '#classLc#', '#type#'],
                        [ucfirst($associationClass), lcfirst($associationClass), ucfirst($type)],
                        '        #classUc##type#Loader $#classLc##type#Loader'
                    );
                    $uses[] = str_replace(
                        ['#classUc#', '#type#'],
                        [ucfirst($associationClass), ucfirst($type)],
                        'use Shopware\#classUc#\Loader\#classUc##type#Loader;'
                    );
                    $init[] = str_replace(
                        ['#associationClassLc#', '#type#'],
                        [lcfirst($associationClass), ucfirst($type)],
                        '$this->#associationClassLc##type#Loader = $#associationClassLc##type#Loader;'
                    );
                    $properties[] = str_replace(
                        ['#classUc#', '#classLc#', '#type#'],
                        [ucfirst($associationClass), lcfirst($associationClass), ucfirst($type)],
                        '
    /**
     * @var #classUc##type#Loader
     */
    private $#classLc##type#Loader;
                        '
                    );
                    break;
                case Util::MANY_TO_ONE:
                    if ($association['fetchTemplate'] !== null) {
                        $fetches[] = $association['fetchTemplate'];
                    } else {
                        $fetches[] = str_replace(
                            ['#associationPlural#', '#classLc#', '#plural#', '#propertyUc#', '#type#'],
                            [lcfirst($associationPlural), lcfirst($associationClass), lcfirst($plural), ucfirst($property), 'Basic'],
                            file_get_contents(__DIR__.'/templates/many_to_one_fetch.txt')
                        );
                    }
                    if ($association['assignTemplate'] !== null) {
                        $assignments[] = $association['assignTemplate'];
                    } else {
                        $assignments[] = str_replace(
                            ['#classLc#', '#propertyUc#', '#associationPlural#'],
                            [lcfirst($class), ucfirst($property), lcfirst($associationPlural)],
                            file_get_contents(__DIR__.'/templates/many_to_one_assignment.txt')
                        );
                    }

                    $constructor[] = str_replace(
                        ['#classUc#', '#classLc#'],
                        [ucfirst($associationClass), lcfirst($associationClass)],
                        '        #classUc#BasicLoader $#classLc#BasicLoader'
                    );
                    $uses[] = str_replace(
                        ['#classUc#'],
                        [ucfirst($associationClass)],
                        'use Shopware\#classUc#\Loader\#classUc#BasicLoader;'
                    );
                    $init[] = str_replace(
                        ['#associationClassLc#'],
                        [lcfirst($associationClass)],
                        '$this->#associationClassLc#BasicLoader = $#associationClassLc#BasicLoader;'
                    );
                    $properties[] = str_replace(
                        ['#classUc#', '#classLc#'],
                        [ucfirst($associationClass), lcfirst($associationClass)],
                        '
    /**
     * @var #classUc#BasicLoader
     */
    private $#classLc#BasicLoader;
                        '
                    );
                    break;
                case Util::ONE_TO_MANY:
                    if ($association['fetchTemplate'] !== null) {
                        $fetches[] = $association['fetchTemplate'];
                    } else {
                        if ($association['has_detail_loader']) {
                            $fetches[] = str_replace(
                                ['#associationPlural#', '#associationTable#', '#table#', '#classLc#', '#associationClassUc#'],
                                [lcfirst($associationPlural), $association['table'], $table, lcfirst($associationClass), ucfirst($associationClass)],
                                file_get_contents(__DIR__.'/templates/one_to_many_fetch_by_loader.txt')
                            );
                        } else {
                            $fetches[] = str_replace(
                                ['#associationPlural#', '#associationTable#', '#table#', '#classLc#', '#associationClassUc#'],
                                [lcfirst($associationPlural), $association['table'], $table, lcfirst($associationClass), ucfirst($associationClass)],
                                file_get_contents(__DIR__.'/templates/one_to_many_fetch.txt')
                            );

                        }
                    }
                    if ($association['assignTemplate'] !== null) {
                        $assignments[] = $association['assignTemplate'];
                    } else {
                        $assignments[] = str_replace(
                            ['#classLc#', '#associationPluralUc#', '#associationPluralLc#', '#classUc#', '#classLc#'],
                            [
                                lcfirst($class),
                                ucfirst($associationPlural),
                                lcfirst($associationPlural),
                                ucfirst($class),
                                lcfirst($class)
                            ],
                            file_get_contents(__DIR__.'/templates/one_to_many_assignment.txt')
                        );
                    }

                    $uses[] = str_replace(
                        ['#classUc#'],
                        [ucfirst($associationClass)],
                        'use Shopware\#classUc#\Searcher\#classUc#SearchResult;'
                    );

                    $constructor[] = str_replace(
                        ['#classUc#', '#classLc#'],
                        [ucfirst($associationClass), lcfirst($associationClass)],
                        '        #classUc#Searcher $#classLc#Searcher'
                    );
                    $uses[] = str_replace(
                        ['#classUc#'],
                        [ucfirst($associationClass)],
                        'use Shopware\#classUc#\Searcher\#classUc#Searcher;'
                    );
                    $init[] = str_replace(
                        ['#classLc#'],
                        [lcfirst($associationClass)],
                        '$this->#classLc#Searcher = $#classLc#Searcher;'
                    );
                    $properties[] = str_replace(
                        ['#classUc#', '#classLc#'],
                        [ucfirst($associationClass), lcfirst($associationClass)],
                        '
    /**
     * @var #classUc#Searcher
     */
    private $#classLc#Searcher;
                        '
                    );

                    if ($association['has_detail_loader']) {
                        $constructor[] = str_replace(
                            ['#classUc#', '#classLc#'],
                            [ucfirst($associationClass), lcfirst($associationClass)],
                            '        #classUc#DetailLoader $#classLc#DetailLoader'
                        );
                        $uses[] = str_replace(
                            ['#classUc#'],
                            [ucfirst($associationClass)],
                            'use Shopware\#classUc#\Loader\#classUc#DetailLoader;'
                        );
                        $init[] = str_replace(
                            ['#classLc#'],
                            [lcfirst($associationClass)],
                            '$this->#classLc#DetailLoader = $#classLc#DetailLoader;'
                        );
                        $properties[] = str_replace(
                            ['#classUc#', '#classLc#'],
                            [ucfirst($associationClass), lcfirst($associationClass)],
                            '
    /**
     * @var #classUc#DetailLoader
     */
    private $#classLc#DetailLoader;
                        '
                        );
                    }

                    break;
                case Util::MANY_TO_MANY:
                    if ($association['fetchTemplate'] !== null) {
                        $fetches[] = $association['fetchTemplate'];
                    } else {
                        $fetches[] = str_replace(
                            ['#associationPlural#', '#associationClassLc#', '#plural#', '#propertyUc#'],
                            [lcfirst($associationPlural), lcfirst($associationClass), lcfirst($plural), ucfirst($property)],
                            file_get_contents(__DIR__.'/templates/many_to_many_fetch.txt')
                        );
                    }
                    if ($association['assignTemplate'] !== null) {
                        $assignments[] = $association['assignTemplate'];
                    } else {
                        $assignments[] = str_replace(
                            ['#classLc#', '#associationPluralUc#', '#associationPluralLc#', '#classLc#', '#classUc#', '#associationClassUc#', '#propertyUc#'],
                            [
                                lcfirst($class),
                                ucfirst($associationPlural),
                                lcfirst($associationPlural),
                                lcfirst($class),
                                ucfirst($class),
                                ucfirst($associationClass),
                                ucfirst($property)
                            ],
                            file_get_contents(__DIR__.'/templates/many_to_many_assignment.txt')
                        );
                    }

                    $constructor[] = str_replace(
                        ['#classUc#', '#classLc#'],
                        [ucfirst($associationClass), lcfirst($associationClass)],
                        '        #classUc#BasicLoader $#classLc#BasicLoader'
                    );
                    $uses[] = str_replace(
                        ['#classUc#'],
                        [ucfirst($associationClass)],
                        'use Shopware\#classUc#\Loader\#classUc#BasicLoader;'
                    );
                    $init[] = str_replace(
                        ['#associationClassLc#'],
                        [lcfirst($associationClass)],
                        '$this->#associationClassLc#BasicLoader = $#associationClassLc#BasicLoader;'
                    );
                    $properties[] = str_replace(
                        ['#classUc#', '#classLc#'],
                        [ucfirst($associationClass), lcfirst($associationClass)],
                        '
    /**
     * @var #classUc#BasicLoader
     */
    private $#classLc#BasicLoader;
                        '
                    );
                    break;


            }
        }

        return array($uses, $properties, $constructor, $init, $fetches, $assignments, $class, $plural);
    }

    private function createServiceXml(string $table, array $config, array $associations, string $type)
    {
        $class = Util::snakeCaseToCamelCase($table);
        $arguments = [];
        foreach ($associations as $association) {
            switch($association['type']) {
                case Util::ONE_TO_ONE:
                    if ($association['has_detail_loader']) {
                        $arguments[] = str_replace('#associationTable#', $association['table'], '            <argument id="shopware.#associationTable#.detail_loader" type="service"/>');
                    } else {
                        $arguments[] = str_replace('#associationTable#', $association['table'], '            <argument id="shopware.#associationTable#.basic_loader" type="service"/>');
                    }
                    break;
                case Util::MANY_TO_ONE:
                    $arguments[] = str_replace('#associationTable#', $association['table'], '            <argument id="shopware.#associationTable#.basic_loader" type="service"/>');
                    break;
                case Util::ONE_TO_MANY:
                    $arguments[] = str_replace('#associationTable#', $association['table'], '            <argument id="shopware.#associationTable#.searcher" type="service"/>');
                    if ($association['has_detail_loader']) {
                        $arguments[] = str_replace('#associationTable#', $association['table'], '            <argument id="shopware.#associationTable#.detail_loader" type="service"/>');
                    }
                    break;
                case Util::MANY_TO_MANY:
                    $arguments[] = str_replace('#associationTable#', $association['table'], '            <argument id="shopware.#associationTable#.basic_loader" type="service"/>');
                    break;
            }
        }
        $arguments = implode("\n", array_unique($arguments));
        if (!empty($arguments)) {
            $arguments = "\n" . $arguments;
        }
        return str_replace(
            ['#classUc#', '#table#', '#associations#', '#typeUc#', '#typeLc#'],
            [ucfirst($class), $table, $arguments, ucfirst($type), lcfirst($type)],
            file_get_contents(__DIR__ . '/templates/loader.xml.txt')
        );
    }
}