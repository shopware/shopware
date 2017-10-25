<?php

namespace ReadGenerator\Event;

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
            $this->directory.'/'.ucfirst($class).'/Event/'.ucfirst($class).'BasicLoadedEvent.php',
            $this->directory.'/'.ucfirst($class).'/Event/'.ucfirst($class).'DetailLoadedEvent.php',
        ];
    }

    public function generate(string $table, array $config)
    {
        $class = Util::snakeCaseToCamelCase($table);
        $plural = Util::getPlural($class);

        $associations = Util::getAssociationsForBasicStruct($table, $config);
        $events = $this->getAssociatedBasicEvent($plural, $associations);
        $uses = $this->getAssociatedBasicEventUsages($associations);

        $events = implode("\n", $events);
        $uses = implode("\n", array_unique($uses));

        $template = str_replace(
            ['#classUc#', '#classLc#', '#pluralLc#', '#pluralUc#', '#events#', '#uses#', '#table#'],
            [ucfirst($class), lcfirst($class), lcfirst($plural), ucfirst($plural), $events, $uses, $table],
            file_get_contents(__DIR__.'/templates/event.txt')
        );

        $file = $this->directory.'/'.ucfirst($class).'/Event/'.ucfirst($class).'BasicLoadedEvent.php';
        file_put_contents($file, $template);
    }


    public function generateDetail(string $table, array $config)
    {
        $class = Util::snakeCaseToCamelCase($table);
        $plural = Util::getPlural($class);

        $associations = Util::getAssociationsForDetailStruct($table, $config);
        $events = $this->getAssociatedBasicEvent($plural, $associations);
        $uses = $this->getAssociatedBasicEventUsages($associations);

        $events = implode("\n", $events);
        $uses = implode("\n", array_unique($uses));

        if (!empty($events)) {
            $events = "\n" . $events;
        }

        $template = str_replace(
            ['#classUc#', '#classLc#', '#pluralLc#', '#pluralUc#', '#events#', '#uses#', '#table#'],
            [ucfirst($class), lcfirst($class), lcfirst($plural), ucfirst($plural), $events, $uses, $table],
            file_get_contents(__DIR__.'/templates/detail_event.txt')
        );

        $file = $this->directory.'/'.ucfirst($class).'/Event/'.ucfirst($class).'DetailLoadedEvent.php';
        file_put_contents($file, $template);
    }

    private function getAssociatedBasicEventUsages(array $associations): array
    {
        $uses = [];
        foreach ($associations as $association) {
            $associationClass = Util::snakeCaseToCamelCase($association['table']);
            $uses[] = str_replace(
                ['#classUc#'],
                [ucfirst($associationClass)],
                'use Shopware\#classUc#\Event\#classUc#BasicLoadedEvent;'
            );
        }
        return $uses;
    }

    private function getAssociatedBasicEvent(string $plural, array $associations): array
    {
        $events = [];

        foreach ($associations as $association) {
            $associationClass = Util::snakeCaseToCamelCase($association['table']);
            $property = Util::getAssociationPropertyName($association);
            $associationPlural = Util::getPlural($property);

            $events[] = str_replace(
                ['#pluralLc#', '#accociationClassUc#', '#associationPluralUc#'],
                [lcfirst($plural), ucfirst($associationClass), ucfirst($associationPlural)],
                '        if ($this->#pluralLc#->get#associationPluralUc#()->count() > 0) {
            $events[] = new #accociationClassUc#BasicLoadedEvent($this->#pluralLc#->get#associationPluralUc#(), $this->context);
        }'
            );
        }
        return $events;
    }

}