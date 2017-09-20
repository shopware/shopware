<?php

namespace ReadGenerator\Collection;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Column;
use ReadGenerator\Util;
class Generator
{
    /** @var  string */
    private $directory;

    /**
     * @var Connection
     */
    private $connection;

    public function __construct($directory, Connection $connection)
    {
        $this->directory = $directory;
        $this->connection = $connection;
    }

    public function generate(string $table, array $config)
    {
        $class = Util::snakeCaseToCamelCase($table);

        $collectiveUuidGetters = $this->createCollectiveUuidGetters($table);

        $associations = Util::getAssociationsForBasicStruct($table, $config);
        $associationGetters = $this->createCollectionAssociationGetters($table, $associations, 'Basic');

        $uses = [];
        foreach ($associations as $association) {
            $associationClass = Util::snakeCaseToCamelCase($association['table']);
            $uses[] = str_replace(
                ['#classUc#'],
                [ucfirst($associationClass)],
                'use Shopware\#classUc#\Struct\#classUc#BasicCollection;'
            );
        }

        $collectivGetters = array_merge($collectiveUuidGetters, $associationGetters);
        if (array_key_exists('collection_functions', $config)) {
            $collectivGetters = array_merge($collectivGetters, $config['collection_functions']);
        }

        $collectivGetters = implode("\n", $collectivGetters);

        $uses = implode("\n", array_unique($uses));

        $template = str_replace(
            ['#classUc#', '#classLc#', '#collectiveUuidGetters#', '#uses#'],
            [ucfirst($class), lcfirst($class), $collectivGetters, $uses],
            file_get_contents(__DIR__.'/templates/collection.txt')
        );

        $file = $this->directory.'/'.ucfirst($class).'/Struct/'.ucfirst($class).'BasicCollection.php';
        file_put_contents($file, $template);
    }

    public function generateDetail(string $table, array $config)
    {
        $class = Util::snakeCaseToCamelCase($table);

        $associations = Util::getAssociationsForDetailStruct($table, $config);
        $associationGetters = $this->createCollectionAssociationGetters($table, $associations, 'Detail');

        $uses = [];
        foreach ($associations as $association) {
            $associationClass = Util::snakeCaseToCamelCase($association['table']);
            $uses[] = str_replace(
                ['#classUc#'],
                [ucfirst($associationClass)],
                'use Shopware\#classUc#\Struct\#classUc#BasicCollection;'
            );
        }

        $associationGetters = implode("\n", $associationGetters);
        $uses = implode("\n", array_unique($uses));

        $template = str_replace(
            ['#classUc#', '#classLc#', '#collectiveUuidGetters#', '#uses#'],
            [ucfirst($class), lcfirst($class), $associationGetters, $uses],
            file_get_contents(__DIR__.'/templates/detail_collection.txt')
        );

        $file = $this->directory.'/'.ucfirst($class).'/Struct/'.ucfirst($class).'DetailCollection.php';

        file_put_contents($file, $template);
    }

    private function createCollectiveUuidGetters(string $table)
    {
        $columns = array_filter(
            $this->getColumns($table),
            function(Column $column) {
                return strpos($column->getName(), '_uuid') !== false;
            }
        );

        $class = Util::snakeCaseToCamelCase($table);

        $getters = [];
        /** @var Column $column */
        foreach ($columns as $column) {
            $columnName = Util::getPropertyName($table, $column->getName());

            $getters[] = str_replace(
                ['#classUc#', '#classLc#', '#propertyUc#'],
                [ucfirst($class), lcfirst($class), ucfirst($columnName)],
                file_get_contents(__DIR__ . '/templates/collection_uuid_getter.txt')
            );

            $getters[] = str_replace(
                ['#classUc#', '#classLc#', '#nameUc#'],
                [ucfirst($class), lcfirst($class), ucfirst($columnName)],
                file_get_contents(__DIR__ . '/templates/collection_uuid_filter.txt')
            );
        }

        return array_unique($getters);
    }

    /**
     * @param string $table
     * @return Column[]
     */
    private function getColumns(string $table): array
    {
        return $this->connection->getSchemaManager()->listTableColumns($table);
    }

    private function createCollectionAssociationGetters($table, $associations, $suffix = 'Basic'): array
    {
        $getters = [];
        $class = Util::snakeCaseToCamelCase($table);

        foreach ($associations as $association) {
            $associationClass = Util::snakeCaseToCamelCase($association['table']);
            $property = Util::getAssociationPropertyName($association);

            $plural = Util::getPlural($property);

            if (Util::isToOne($association['type'])) {
                $getters[] = str_replace(
                    ['#pluralUc#', '#associationClassUc#', '#classUc#', '#classLc#', '#suffix#', '#propertyUc#'],
                    [ucfirst($plural), ucfirst($associationClass), ucfirst($class), lcfirst($class), ucfirst($suffix), ucfirst($property)],
                    file_get_contents(__DIR__ . '/templates/collective_to_one_association_getter.txt')
                );

                continue;
            }
            if ($association['type'] === Util::ONE_TO_MANY) {
                $getters[] =  str_replace(
                    ['#classUc#', '#pluralUc#'],
                    [ucfirst($property), ucfirst($plural)],
                    file_get_contents(__DIR__ . '/templates/collective_one_to_many_uuid_getter.txt')
                );
                $getters[] = str_replace(
                    ['#pluralUc#', '#associationClassUc#'],
                    [ucfirst($plural), ucfirst($associationClass)],
                    file_get_contents(__DIR__ . '/templates/collective_to_many_association_getter.txt')
                );
                continue;
            }
            $getters[] =  str_replace(
                ['#classUc#'],
                [ucfirst($property)],
                file_get_contents(__DIR__ . '/templates/collective_many_to_many_uuid_getter.txt')
            );
            $getters[] = str_replace(
                ['#pluralUc#', '#associationClassUc#'],
                [ucfirst($plural), ucfirst($associationClass)],
                file_get_contents(__DIR__ . '/templates/collective_to_many_association_getter.txt')
            );
        }

        return $getters;
    }

}