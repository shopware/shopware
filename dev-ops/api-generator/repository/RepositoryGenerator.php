<?php

class RepositoryGenerator
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
        /** @var TableDefinition $definition */
        foreach ($definitions as $definition) {
            if ($definition->isMappingTable) {
                continue;
            }
            $this->generateRepository($definition, $context);
        }
    }

    private function generateRepository(TableDefinition $definition, Context $context)
    {
        $template = file_get_contents(__DIR__ . '/template.txt');

        $detail = '     return $this->readBasic($uuids, $context);';
        $detailCollection = 'BasicCollection';

        $uses = [];

        if ($definition->hasDetail()) {
            $uses[] = sprintf('use Shopware\\Api\\%s\\Collection\\%sDetailCollection;', ucfirst($definition->bundle), ucfirst($definition->domainName));
            $uses[] = sprintf('use Shopware\\Api\\%s\\Event\\%s\\%sDetailLoadedEvent;', ucfirst($definition->bundle), ucfirst($definition->domainName), ucfirst($definition->domainName));

            $detailCollection = 'DetailCollection';
            $detail = str_replace(
                ['#classUc#'],
                [ucfirst($definition->domainName)],
                '
        /** @var #classUc#DetailCollection $entities */
        $entities = $this->reader->readDetail(#classUc#Definition::class, $uuids, $context);

        $event = new #classUc#DetailLoadedEvent($entities, $context);
        $this->eventDispatcher->dispatch($event->getName(), $event);

        return $entities;                
                '
            );

        }

        $template = str_replace(
            ['#bundle#', '#classUc#', '#detailRead#', '#detailCollection#', '#uses#'],
            [
                ucfirst($definition->bundle),
                ucfirst($definition->domainName),
                $detail,
                $detailCollection,
                implode("\n", $uses)
            ],
            $template
        );

        $file = $this->directory . '/' . ucfirst($definition->bundle) . '/Repository/' . ucfirst($definition->domainName) . 'Repository.php';

        file_put_contents($file, $template);
    }

}