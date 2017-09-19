<?php

namespace ReadGenerator\Extension;

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

    public function generate(string $table, array $config): void
    {
        $class = Util::snakeCaseToCamelCase($table);

        $template = __DIR__ . '/templates/extension.txt';
        if (Util::getAssociationsForDetailStruct($table, $config)) {
            $template = __DIR__ . '/templates/extension_detail.txt';
        }

        $template = str_replace(
            ['#classUc#', '#classLc#'],
            [ucfirst($class), lcfirst($class)],
            file_get_contents($template)
        );

        $file = $this->directory.'/'.ucfirst($class).'/Extension/'.ucfirst($class).'Extension.php';

        file_put_contents($file, $template);
    }
}