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

    public function generate(string $table, array $config): string
    {
        $class = Util::snakeCaseToCamelCase($table);
        $plural = Util::getPlural($class);

        $content = str_replace(
            ['#classUc#', '#classLc#', '#plural#', '#table#'],
            [ucfirst($class), lcfirst($class), lcfirst($plural), $table],
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