<?php

namespace ReadGenerator\Repository;

use ReadGenerator\Util;

class Generator
{
    /**
     * @var string
     */
    private $directory;

    /**
     * @var string
     */
    private $argumentWriterTemplate;

    /**
     * @var string
     */
    private $writeMethodsTemplate;

    public function __construct($directory)
    {
        $this->directory = $directory;
        $this->argumentWriterTemplate = file_get_contents(__DIR__ . '/templates/services_argument_writer.txt');
        $this->writeMethodsTemplate = file_get_contents(__DIR__ . '/templates/repository_write_methods.txt');
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

        $writeMethods = $this->writeMethodsTemplate;
        $constructor = file_get_contents(__DIR__ . '/templates/repository_constructor.txt');

        $template = __DIR__ . '/templates/repository.txt';
        if (Util::getAssociationsForDetailStruct($table, $config)) {
            $template = __DIR__ . '/templates/repository_detail.txt';
            $constructor = file_get_contents(__DIR__ . '/templates/repository_detail_constructor.txt');
        }

        $writeMethods = str_replace('#classUc#', ucfirst($class), $writeMethods);
        $constructor = str_replace('#classUc#', ucfirst($class), $constructor);

        $template = str_replace(
            ['#classUc#', '#constructor#', '#writeMethods#'],
            [ucfirst($class), $constructor, $writeMethods],
            file_get_contents($template)
        );

        $file = $this->directory.'/'.ucfirst($class).'/Repository/'.ucfirst($class).'Repository.php';

        file_put_contents($file, $template);

        return $this->createServicesXml($table, $config);
    }

    private function createServicesXml($table, $config)
    {
        $class = Util::snakeCaseToCamelCase($table);

        $argumentWriter = $this->argumentWriterTemplate;
        $argumentWriter = str_replace(
            ['#classUc#', '#classLc#', '#table#', '#argumentWriter#'],
            [ucfirst($class), lcfirst($class), $table, $argumentWriter],
            $argumentWriter
        );

        if (Util::getAssociationsForDetailStruct($table, $config)) {
            return str_replace(
                ['#classUc#', '#classLc#', '#table#', '#argumentWriter#'],
                [ucfirst($class), lcfirst($class), $table, $argumentWriter],
                file_get_contents(__DIR__ . '/templates/repository_detail.xml.txt')
            );
        }
        return str_replace(
            ['#classUc#', '#classLc#', '#table#', '#argumentWriter#'],
            [ucfirst($class), lcfirst($class), $table, $argumentWriter],
            file_get_contents(__DIR__ . '/templates/repository.xml.txt')
        );
    }
}