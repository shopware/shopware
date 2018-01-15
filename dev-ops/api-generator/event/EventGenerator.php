<?php

class EventGenerator
{
    /**
     * @var string
     */
    private $directory;

    public function __construct($directory)
    {
        $this->directory = $directory;
    }
    
    public function generate(array $definitions, Context $context)
    {
        foreach ($definitions as $definition) {
            $this->generateEvents($definition, $context);
        }
    }

    private function generateEvents(TableDefinition $definition, Context $context)
    {
        $dir = sprintf(
            $this->directory.'/%s/Event/%s',
            ucfirst($definition->bundle),
            ucfirst($definition->domainName)
        );

        if (!file_exists($dir)) {
            mkdir($dir);
        }
        $this->createWrittenEvent($definition);

        $this->createDeletedEvent($definition);

        if ($definition->isMappingTable) {
            return;
        }

        $this->createBasicLoadedEvent($definition, $context);

        $this->createSearchResultEvent($definition);

        $this->createAggregationResultEvent($definition);

        $this->createIdSearchResultEvent($definition);

        if ($definition->hasDetail()) {
            $this->generateDetail($definition, $context);
        }
    }

    /**
     * @param TableDefinition $definition
     */
    private function createWrittenEvent(TableDefinition $definition): void
    {
        $template = file_get_contents(__DIR__.'/written_event.txt');
        $template = str_replace(
            ['#bundle#', '#classUc#', '#table#'],
            [ucfirst($definition->bundle), ucfirst($definition->domainName), $definition->tableName],
            $template
        );

        $file = sprintf(
            $this->directory.'/%s/Event/%s/%sWrittenEvent.php',
            ucfirst($definition->bundle),
            ucfirst($definition->domainName),
            ucfirst($definition->domainName)
        );

        file_put_contents($file, $template);
    }

    private function createBasicLoadedEvent(TableDefinition $definition)
    {
        $template = file_get_contents(__DIR__.'/basic_loaded_event.txt');

        $basics = array_filter($definition->associations, function(Association $association){
            return $association->inBasic && !$association->writeOnly;
        });

        $nested = $this->buildNestedLoadedEvents($definition, $basics);

        $template = str_replace(
            ['#bundle#', '#classUc#', '#table#', '#pluralLc#', '#pluralUc#', '#events#', '#uses#'],
            [
                ucfirst($definition->bundle),
                ucfirst($definition->domainName),
                $definition->tableName,
                lcfirst($definition->domainNameInPlural),
                ucfirst($definition->domainNameInPlural),
                $nested['template'],
                implode("\n", $nested['uses'])
            ],
            $template
        );
        

        $file = sprintf(
            $this->directory.'/%s/Event/%s/%sBasicLoadedEvent.php',
            ucfirst($definition->bundle),
            ucfirst($definition->domainName),
            ucfirst($definition->domainName)
        );

        file_put_contents($file, $template);
    }

    private function buildNestedLoadedEvents(TableDefinition $definition, array $basics)
    {
        $events = [];
        $uses = [];

        $plural = $definition->domainNameInPlural;
        /** @var Association $association */
        foreach ($basics as $association) {
            if ($association->writeOnly) {
                continue;
            }

            $associationClass = $association->referenceTableDomainName;
            $associationPlural = $association->propertyPlural;

            $uses[] = str_replace(
                ['#bundle#', '#classUc#'],
                [ucfirst($association->referenceBundle), ucfirst($associationClass)],
                'use Shopware\\Api\\#bundle#\\Event\\#classUc#\\#classUc#BasicLoadedEvent;'
            );
            if ($association instanceof ManyToManyAssociation) {
                $associationPlural = 'all' . ucfirst($associationPlural);
            }

            $events[] = str_replace(
                ['#pluralLc#', '#accociationClassUc#', '#associationPluralUc#'],
                [lcfirst($plural), ucfirst($associationClass), ucfirst($associationPlural)],
                '        if ($this->#pluralLc#->get#associationPluralUc#()->count() > 0) {
            $events[] = new #accociationClassUc#BasicLoadedEvent($this->#pluralLc#->get#associationPluralUc#(), $this->context);
        }'
            );
        }

        if (empty($events)) {
            return ['uses' => [], 'template' => ''];
        }

        $template = str_replace(
            '#events#',
            implode("\n", $events),
            '
    public function getEvents(): ?NestedEventCollection
    {
        $events = [];
#events#
        return new NestedEventCollection($events);
    }            
            '
        );

        return ['uses' => array_unique($uses), 'template' => $template];
    }

    private function generateDetail($definition, Context $context)
    {
        $template = file_get_contents(__DIR__.'/detail_loaded_event.txt');

        $nested = $this->buildNestedLoadedEvents($definition, $definition->associations, $context);

        $template = str_replace(
            ['#bundle#', '#classUc#', '#table#', '#pluralLc#', '#pluralUc#', '#events#', '#uses#'],
            [
                ucfirst($definition->bundle),
                ucfirst($definition->domainName),
                $definition->tableName,
                lcfirst($definition->domainNameInPlural),
                ucfirst($definition->domainNameInPlural),
                $nested['template'],
                implode("\n", $nested['uses'])
            ],
            $template
        );

        $file = sprintf(
            $this->directory.'/%s/Event/%s/%sDetailLoadedEvent.php',
            ucfirst($definition->bundle),
            ucfirst($definition->domainName),
            ucfirst($definition->domainName)
        );

        file_put_contents($file, $template);
    }

    private function createSearchResultEvent(TableDefinition $definition)
    {
        $template = file_get_contents(__DIR__.'/search_result_event.txt');

        $template = str_replace(
            ['#bundle#', '#classUc#', '#table#'],
            [
                ucfirst($definition->bundle),
                ucfirst($definition->domainName),
                $definition->tableName
            ],
            $template
        );

        $file = sprintf(
            $this->directory.'/%s/Event/%s/%sSearchResultLoadedEvent.php',
            ucfirst($definition->bundle),
            ucfirst($definition->domainName),
            ucfirst($definition->domainName)
        );

        file_put_contents($file, $template);
    }

    private function createIdSearchResultEvent(TableDefinition $definition)
    {
        $template = file_get_contents(__DIR__.'/id_search_result.txt');

        $template = str_replace(
            ['#bundle#', '#classUc#', '#table#'],
            [
                ucfirst($definition->bundle),
                ucfirst($definition->domainName),
                $definition->tableName
            ],
            $template
        );

        $file = sprintf(
            $this->directory.'/%s/Event/%s/%sIdSearchResultLoadedEvent.php',
            ucfirst($definition->bundle),
            ucfirst($definition->domainName),
            ucfirst($definition->domainName)
        );

        file_put_contents($file, $template);
    }

    private function createAggregationResultEvent(TableDefinition $definition)
    {
        $template = file_get_contents(__DIR__.'/aggregation_result.txt');

        $template = str_replace(
            ['#bundle#', '#classUc#', '#table#'],
            [
                ucfirst($definition->bundle),
                ucfirst($definition->domainName),
                $definition->tableName
            ],
            $template
        );

        $file = sprintf(
            $this->directory.'/%s/Event/%s/%sAggregationResultLoadedEvent.php',
            ucfirst($definition->bundle),
            ucfirst($definition->domainName),
            ucfirst($definition->domainName)
        );

        file_put_contents($file, $template);
    }

    private function createDeletedEvent($definition): void
    {
        $template = file_get_contents(__DIR__.'/deleted_event.txt');
        $template = str_replace(
            ['#bundle#', '#classUc#', '#table#'],
            [ucfirst($definition->bundle), ucfirst($definition->domainName), $definition->tableName],
            $template
        );

        $file = sprintf(
            $this->directory.'/%s/Event/%s/%sDeletedEvent.php',
            ucfirst($definition->bundle),
            ucfirst($definition->domainName),
            ucfirst($definition->domainName)
        );

        file_put_contents($file, $template);
    }
}