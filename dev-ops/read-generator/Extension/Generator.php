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

    public function getFiles($table)
    {
        $class = Util::snakeCaseToCamelCase($table);
        return [
            $this->directory.'/'.ucfirst($class).'/Extension/'.ucfirst($class).'Extension.php'
        ];
    }


    public function generate(string $table, array $config): void
    {
        $class = Util::snakeCaseToCamelCase($table);

        $writeMethod = '';
        $writeSubscribe = '';

        if (preg_match('#_ro$#i', $table)) {
            $writeMethod = file_get_contents(__DIR__ . '/templates/extension_write_method.txt');
            $writeSubscribe = file_get_contents(__DIR__ . '/templates/extension_write_subscribe.txt');
        }

        $template = __DIR__ . '/templates/extension.txt';
        if (Util::getAssociationsForDetailStruct($table, $config)) {
            $template = __DIR__ . '/templates/extension_detail.txt';
        }

        $template = file_get_contents($template);

        $template = str_replace(
            ['#writeMethod#', '#writeSubscribe#'],
            [$writeMethod, $writeSubscribe],
            $template
        );

        $template = str_replace(
            ['#classUc#', '#classLc#'],
            [ucfirst($class), lcfirst($class)],
            $template
        );

        $file = $this->directory.'/'.ucfirst($class).'/Extension/'.ucfirst($class).'Extension.php';

        file_put_contents($file, $template);
    }
}