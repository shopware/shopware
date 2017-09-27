<?php

use Doctrine\DBAL\Connection;
use ReadGenerator\Util;
require_once __DIR__ . '/Util.php';
require_once __DIR__ . '/Bundle/Generator.php';
require_once __DIR__ . '/Collection/Generator.php';
require_once __DIR__ . '/Event/Generator.php';
require_once __DIR__ . '/Factory/Generator.php';
require_once __DIR__ . '/Struct/Generator.php';
require_once __DIR__ . '/Loader/Generator.php';
require_once __DIR__ . '/Searcher/Generator.php';
require_once __DIR__ . '/Repository/Generator.php';
require_once __DIR__ . '/Extension/Generator.php';
require_once __DIR__ . '/Controller/Generator.php';
require_once __DIR__ . '/Writer/Generator.php';

class DomainGenerator
{
    const DEFAULT_CONFIG = [
        'create_detail' => false,
        'seo_url_name' => '',
        'columns' => [],
        'associations' => [],
        'has_translation' => true
    ];

    /**
     * @var Connection
     */
    protected $connection;

    /**
     * @var string
     */
    protected $directory;

    public function __construct(Connection $connection, string $directory)
    {
        $this->connection = $connection;
        $this->directory = $directory;
    }

    public function generate(string $table, array $config, \ReadGenerator\Context $context): void
    {
        $config = array_replace_recursive(self::DEFAULT_CONFIG, $config);
        $services = [];

        $this->createDirectories($table, $config);

        if ($context->createStruct) {
            $generator = new \ReadGenerator\Struct\Generator($this->directory, $this->connection);
            $this->removeFiles($generator->getFiles($table));
            $generator->generate($table, $config);
            if (Util::getAssociationsForDetailStruct($table, $config)) {
                $generator->generateDetail($table, $config);
            }
        }

        if ($context->createCollection) {
            $generator = new \ReadGenerator\Collection\Generator($this->directory, $this->connection);
            $this->removeFiles($generator->getFiles($table));
            $generator->generate($table, $config);
            if (Util::getAssociationsForDetailStruct($table, $config)) {
                $generator->generateDetail($table, $config);
            }
        }

        if ($context->createFactory) {
            $generator = new \ReadGenerator\Factory\Generator($this->directory, $this->connection);
            $this->removeFiles($generator->getFiles($table));
            $services[] = $generator->generate($table, $config);
            if (Util::getAssociationsForDetailStruct($table, $config)) {
                $services[] = $generator->generateDetail($table, $config);
            }
        }

        if ($context->createExtension) {
            $generator = new \ReadGenerator\Extension\Generator($this->directory);
            $this->removeFiles($generator->getFiles($table));
            $generator->generate($table, $config);
        }

        if ($context->createController) {
            $generator = new \ReadGenerator\Controller\Generator($this->directory);
            $this->removeFiles($generator->getFiles($table));
            $services[] = $generator->generate($table, $config);
        }

        if ($context->createWriter) {
            $generator = new \ReadGenerator\Writer\Generator($this->directory);
            $this->removeFiles($generator->getFiles($table));
            $services[] = $generator->generate($table, $config);
        }

        if ($context->createBundle) {
            $generator = new \ReadGenerator\Bundle\Generator($this->directory);
            $generator->generate($table);
        }

        if ($context->createEvent) {
            $generator = new \ReadGenerator\Event\Generator($this->directory);
            $this->removeFiles($generator->getFiles($table));
            $generator->generate($table, $config);
            if (Util::getAssociationsForDetailStruct($table, $config)) {
                $generator->generateDetail($table, $config);
            }
        }

        if ($context->createLoader) {
            $generator = new \ReadGenerator\Loader\Generator($this->directory);
            $this->removeFiles($generator->getFiles($table));
            $services[] = $generator->generate($table, $config);
            if (Util::getAssociationsForDetailStruct($table, $config)) {
                $services[] = $generator->generateDetail($table, $config);
            }
        }

        if ($context->createSearcher) {
            $generator = new \ReadGenerator\Searcher\Generator($this->directory);
            $this->removeFiles($generator->getFiles($table));
            $services[] = $generator->generate($table, $config);
            $generator->generateSearchResult($table);
        }

        if ($context->createRepository) {
            $generator = new \ReadGenerator\Repository\Generator($this->directory);
            $this->removeFiles($generator->getFiles($table));
            $services[] = $generator->generate($table, $config);
        }

        if ($context->createServiceXml) {
            $this->createSevicesXml($table, $services);
        }
    }

    private function removeFiles(array $files): void
    {
        foreach ($files as $file) {
            if (file_exists($file)) {
                unlink($file);
            }
        }
    }

    private function createDirectories($table, $config)
    {
        $class = Util::snakeCaseToCamelCase($table);

        $dirs = [
            $this->directory,
            $this->directory.'/'.ucfirst($class),
            $this->directory.'/'.ucfirst($class).'/DependencyInjection',
            $this->directory.'/'.ucfirst($class).'/Extension',
            $this->directory.'/'.ucfirst($class).'/Event',
            $this->directory.'/'.ucfirst($class).'/Repository',
            $this->directory.'/'.ucfirst($class).'/Loader',
            $this->directory.'/'.ucfirst($class).'/Searcher',
            $this->directory.'/'.ucfirst($class).'/Factory',
            $this->directory.'/'.ucfirst($class).'/Struct',
            $this->directory.'/'.ucfirst($class).'/Controller',
            $this->directory.'/'.ucfirst($class).'/Writer'
        ];

        foreach ($dirs as $dir) {
            if (!file_exists($dir)) {
                mkdir($dir);
            }
        }
    }

    private function createSevicesXml($table, array $services): void
    {
        $class = Util::snakeCaseToCamelCase($table);

        $template = str_replace('#services#', implode("\n", $services), file_get_contents(__DIR__ . '/services.xml.txt'));

        $file = $this->directory.'/'.ucfirst($class).'/DependencyInjection/read_services.xml';

        file_put_contents($file, $template);
    }
}