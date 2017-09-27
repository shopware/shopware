<?php

namespace ReadGenerator\Writer;

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
            $this->directory.'/'.ucfirst($class).'/Writer/'.ucfirst($class).'Writer.php',
            $this->directory.'/'.ucfirst($class).'/Event/'.ucfirst($class). 'WriteExtenderEvent.php'
        ];
    }

    public function generate(string $table, array $config): string
    {
        $class = Util::snakeCaseToCamelCase($table);

        $resourceUse = '';
        if ($table !== 'shop') {
            $resourceUse = 'use Shopware\#classUc#\Writer\Resource\#classUc#Resource;';
        }
        $content = str_replace(
            ['#resourceUse#'],
            [$resourceUse],
            file_get_contents(__DIR__ . '/templates/writer.txt')
        );

        $content = str_replace(
            ['#classUc#', '#classLc#'],
            [ucfirst($class), lcfirst($class)],
            $content
        );

        $file = $this->directory.'/'.ucfirst($class).'/Writer/'.ucfirst($class).'Writer.php';

        file_put_contents($file, $content);

        $this->generateExtenderEvent($table);

        return $this->createServicesXml($table);
    }

    private function createServicesXml(string $table): string
    {
        $class = Util::snakeCaseToCamelCase($table);
        $template = '
        <service class="Shopware\#classUc#\Writer\#classUc#Writer" id="shopware.#table#.writer">
            <argument type="service" id="shopware.framework.write.field_aware.default_extender" />
            <argument type="service" id="event_dispatcher" />
            <argument type="service" id="shopware.framework.write.writer" />
        </service>
        ';

        return str_replace(
            ['#classUc#', '#table#'],
            [ucfirst($class), $table],
            $template
        );
    }

    private function generateExtenderEvent(string $table): void
    {
        $class = Util::snakeCaseToCamelCase($table);

        $template = str_replace(
            ['#classUc#', '#table#'],
            [ucfirst($class), $table],
            file_get_contents(__DIR__ . '/templates/extender_event.txt')
        );

        $file = $this->directory.'/'.ucfirst($class).'/Event/'.ucfirst($class). 'WriteExtenderEvent.php';

        file_put_contents($file, $template);
    }
}