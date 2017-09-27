<?php

namespace ReadGenerator\Test;

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

    public function getFiles($table): array
    {
        $class = Util::snakeCaseToCamelCase($table);

        return [
            $this->directory.'/'.ucfirst($class).'/Test/Repository/'.ucfirst($class).'RepositoryTest.php',
        ];
    }

    public function generate($table)
    {
        $class = Util::snakeCaseToCamelCase($table);

        $content = str_replace(
            ['#classUc#', '#table#'],
            [ucfirst($class), $table],
            file_get_contents(__DIR__ . '/templates/repository_test.txt')
        );

        $file = $this->directory.'/'.ucfirst($class).'/Test/Repository/'.ucfirst($class).'RepositoryTest.php';

        file_put_contents($file, $content);
    }
}