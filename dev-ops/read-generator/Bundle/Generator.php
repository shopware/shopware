<?php

namespace ReadGenerator\Bundle;
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

    public function generate($table): void
    {
        $class = Util::snakeCaseToCamelCase($table);

        $template = str_replace(
            ['#classUc#'],
            [ucfirst($class)],
            file_get_contents(__DIR__.'/templates/bundle.txt')
        );
        $file = $this->directory . '/'.ucfirst($class).'/'.ucfirst($class).'.php';

        file_put_contents($file, $template);
    }
}