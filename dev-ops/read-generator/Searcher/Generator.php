<?php

namespace ReadGenerator\Searcher;

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

    public function generate(string $table, array $config)
    {
        $class = Util::snakeCaseToCamelCase($table);

        $factoryType = 'Basic';
        if (Util::getAssociationsForDetailStruct($table, $config)) {
            $factoryType = 'Detail';
        }

        $template = str_replace(
            ['#classUc#', '#factoryType#'],
            [ucfirst($class), ucfirst($factoryType)],
            file_get_contents(__DIR__ . '/templates/searcher.txt')
        );

        $file = $this->directory.'/'.ucfirst($class).'/Searcher/'.ucfirst($class).'Searcher.php';

        file_put_contents($file, $template);

        return $this->createServicesXml($table, $config, $factoryType);
    }

    public function generateSearchResult(string $table)
    {
        $class = Util::snakeCaseToCamelCase($table);

        $template = str_replace(
            ['#classUc#'],
            [ucfirst($class)],
            file_get_contents(__DIR__.'/templates/search_result.txt')
        );

        $file = $this->directory.'/'.ucfirst($class).'/Searcher/'.ucfirst($class).'SearchResult.php';
        file_put_contents($file, $template);
    }

    private function createServicesXml($table, $config, $factoryType)
    {
        $class = Util::snakeCaseToCamelCase($table);

        return str_replace(
            ['#classUc#', '#table#', '#factoryType#'],
            [ucfirst($class), $table, lcfirst($factoryType)],
            file_get_contents(__DIR__ . '/templates/searcher.xml.txt')
        );
    }
}