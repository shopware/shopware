<?php

namespace ReadGenerator\Repository;

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
            $this->directory.'/'.ucfirst($class).'/Repository/'.ucfirst($class).'Repository.php'
        ];
    }

    public function generate(string $table, array $config)
    {
        $class = Util::snakeCaseToCamelCase($table);

        $template = __DIR__ . '/templates/repository.txt';
        if (Util::getAssociationsForDetailStruct($table, $config)) {
            $template = __DIR__ . '/templates/repository_detail.txt';
        }

        $template = str_replace(['#classUc#'], [ucfirst($class)], file_get_contents($template));

        $file = $this->directory.'/'.ucfirst($class).'/Repository/'.ucfirst($class).'Repository.php';

        file_put_contents($file, $template);

        return $this->createServicesXml($table, $config);
    }

    private function createServicesXml($table, $config)
    {
        $class = Util::snakeCaseToCamelCase($table);

        if (Util::getAssociationsForDetailStruct($table, $config)) {
            return str_replace(
                ['#classUc#', '#classLc#', '#table#'],
                [ucfirst($class), lcfirst($class), $table],
                file_get_contents(__DIR__ . '/templates/repository_detail.xml.txt')
            );
        }
        return str_replace(
            ['#classUc#', '#classLc#', '#table#'],
            [ucfirst($class), lcfirst($class), $table],
            file_get_contents(__DIR__ . '/templates/repository.xml.txt')
        );
    }
}