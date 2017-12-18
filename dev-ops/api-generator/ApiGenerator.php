<?php

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Identifier;
use Doctrine\DBAL\Schema\Index;

class ApiGenerator
{
    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var AbstractSchemaManager
     */
    private $schemaManager;

    /**
     * @var string
     */
    private $outputDirectory;

    private $ignoreTables = [
        'cart', 'sessions', 'session', 'schema_version', 'search_keyword'
    ];

    public function __construct($outputDirectory = __DIR__ . '/output')
    {
        $connection = \Shopware\Framework\Doctrine\DatabaseConnector::createPdoConnection();

        $this->connection = new Connection(
            ['pdo' => $connection, 'platform' => new \Doctrine\DBAL\Platforms\MySQL57Platform()],
            new \Doctrine\DBAL\Driver\PDOMySql\Driver(),
            null,
            null
        );
        $this->schemaManager = $this->connection->getSchemaManager();
        $this->outputDirectory = $outputDirectory;
    }

    public function generate(Context $context)
    {
        $tables = $this->readTables();

        $definitions = (new StructureCollector($this->schemaManager))->collect($tables, $context);

        /** @var TableDefinition $definition */
        foreach ($definitions as $definition) {
            $dir = rtrim($this->outputDirectory, '/') . '/' . ucfirst($definition->domainName);

            if (!file_exists($dir)) {
                continue;
            }
        }

        if (!file_exists($this->outputDirectory)) {
            mkdir($this->outputDirectory);
        }

        foreach ($definitions as $definition) {
            if (strpos($definition->tableName, '_translation') !== false) {
                continue;
            }
            $this->createDirectories($definition);
        }

        $generator = new DefinitionGenerator($this->outputDirectory);
        $generator->generate($definitions, $context);

        $generator = new CollectionGenerator($this->outputDirectory);
        $generator->generate($definitions, $context);

        $generator = new StructGenerator($this->outputDirectory);
        $generator->generate($definitions, $context);

        $generator = new RepositoryGenerator($this->outputDirectory);
        $generator->generate($definitions, $context);

        $generator = new EventGenerator($this->outputDirectory);
        $generator->generate($definitions, $context);

        $generator = new SearchResultGenerator($this->outputDirectory);
        $generator->generate($definitions, $context);

        $this->createServicesXmlsAndBundles($definitions);
    }


    /**
     * @return array
     */
    private function readTables(): array
    {
        $tables = $this->schemaManager->listTableNames();
        $tables = array_filter(
            $tables,
            function ($table) {
                if (in_array($table, $this->ignoreTables, true)) {
                    return false;
                }
                if (strpos($table, 's_') === 0) {
                    return false;
                }
                if (strpos($table, '_attribute') !== false) {
                    return false;
                }

                return true;
            }
        );

        return $tables;
    }

    private function createDirectories(TableDefinition $definition)
    {
        $name = ucfirst($definition->bundle);

        $base = $this->outputDirectory . '/' . $name;
        if (!file_exists($base)) {
            mkdir($base);
        }

        $directories = [
            'Definition',
            'Collection',
            'DependencyInjection',
            'Struct',
            'Event',
            'Repository',
        ];

        foreach ($directories as $dir) {
            $tmp = $base . '/' . $dir;
            if (!file_exists($tmp)) {
                mkdir($tmp);
            }
        }
    }

    private function createServicesXmlsAndBundles(array $definitions)
    {
        $services = [];
        /** @var TableDefinition $definition */
        foreach ($definitions as $definition) {


            $services[$definition->bundle][] = str_replace(
                ['#bundleLc#', '#table#', '#bundleUc#', '#classUc#'],
                [lcfirst($definition->bundle), $definition->tableName, ucfirst($definition->bundle), ucfirst($definition->domainName)],
                '         <service class="Shopware\Api\#bundleUc#\Definition\#classUc#Definition" id="shopware.#bundleLc#.#table#_definition" >
            <tag name="shopware.entity.definition" entity="#table#" />
        </service>'
            );

            if (!$definition->isMappingTable) {
                $services[$definition->bundle][] = str_replace(
                    ['#bundleLc#', '#table#', '#bundleUc#', '#classUc#'],
                    [lcfirst($definition->bundle), $definition->tableName, ucfirst($definition->bundle), ucfirst($definition->domainName)],
                    '        <service class="Shopware\Api\#bundleUc#\Repository\#classUc#Repository" id="Shopware\Api\#bundleUc#\Repository\#classUc#Repository" public="true">
          <argument id="shopware.api.entity_reader" type="service"/>
          <argument id="shopware.api.entity_writer" type="service"/>
          <argument id="shopware.api.entity_searcher" type="service"/>
          <argument id="shopware.api.entity_aggregator" type="service"/>
          <argument id="event_dispatcher" type="service"/>
        </service>'
                );
            }
        }

        foreach ($services as $bundle => $bundleServices) {
            $dir = $this->outputDirectory . '/' . ucfirst($bundle);

            $file = $dir . '/DependencyInjection/api.xml';
            if (file_exists($file)) {
                unlink($file);
            }

            $template = str_replace(
                ['#services#'],
                [
                    implode("\n", $bundleServices)
                ],
                '<?xml version="1.0" ?>

<container xmlns="http://symfony.com/schema/dic/services"
           xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
           xsi:schemaLocation="http://symfony.com/schema/dic/services http://symfony.com/schema/dic/services/services-1.0.xsd">

    <services>
#services#
    </services>
</container>                
                '
            );

            file_put_contents($file, $template);

        }
    }
}