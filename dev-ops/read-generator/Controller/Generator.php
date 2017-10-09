<?php

namespace ReadGenerator\Controller;

use ReadGenerator\Util;

class Generator
{
    /**
     * @var string
     */
    private $directory;

    public function __construct(string $directory)
    {
        $this->directory = $directory;
    }

    public function getFiles($table)
    {
        $class = Util::snakeCaseToCamelCase($table);
        return [
            $this->directory.'/'.ucfirst($class).'/Controller/'.ucfirst($class).'Controller.php'
        ];
    }

    public function generate(string $table, array $config): string
    {
        $class = Util::snakeCaseToCamelCase($table);
        $plural = Util::getPlural($class);

        $detailRead = 'read';
        if (Util::getAssociationsForDetailStruct($table, $config)) {
            $detailRead = 'readDetail';
        }

        $writeActions = $readActions = '';

        $readActions = str_replace(
            ['#classUc#', '#classLc#', '#plural#', '#table#', '#detailRead#'],
            [ucfirst($class), lcfirst($class), lcfirst($plural), $table, $detailRead],
            file_get_contents(__DIR__ . '/templates/read_actions.txt')
        );

        if ((bool) preg_match('#_ro$#i', $table) === false) {
            $writeActions = str_replace(
                ['#classUc#', '#classLc#', '#plural#', '#table#', '#detailRead#'],
                [ucfirst($class), lcfirst($class), lcfirst($plural), $table, $detailRead],
                file_get_contents(__DIR__ . '/templates/write_actions.txt')
            );
        }

        $content = str_replace(
            ['#classUc#', '#classLc#', '#plural#', '#table#', '#detailRead#', '#readActions#', '#writeActions#'],
            [ucfirst($class), lcfirst($class), lcfirst($plural), $table, $detailRead, $readActions, $writeActions],
            file_get_contents(__DIR__ . '/templates/controller.txt')
        );

        $file = $this->directory.'/'.ucfirst($class).'/Controller/'.ucfirst($class).'Controller.php';
        file_put_contents($file, $content);
        
        return $this->createServicesXml($table);
    }

    private function createServicesXml($table): string
    {
        $template = '
        <service class="Shopware\#classUc#\Controller\#classUc#Controller" id="shopware.#table#.api_controller">
            <argument id="shopware.#table#.repository" type="service"/>
        </service>
        ';

        $class = Util::snakeCaseToCamelCase($table);

        return str_replace(
            ['#table#', '#classUc#'],
            [$table, ucfirst($class)],
            $template
        );
    }
}