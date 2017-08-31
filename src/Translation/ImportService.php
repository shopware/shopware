<?php

namespace Shopware\Translation;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DBALException;
use Psr\Log\LoggerInterface;
use Shopware\Translation\Event\ImportAdvanceEvent;
use Shopware\Translation\Event\ImportFinishEvent;
use Shopware\Translation\Event\ImportStartEvent;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;

class ImportService implements ImportServiceInterface
{
    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var EventDispatcherInterface
     */
    private $event;

    /**
     * @var LoggerInterface
     */
    private $logger;

    public function __construct(Connection $connection, EventDispatcherInterface $event, LoggerInterface $logger)
    {
        $this->connection = $connection;
        $this->event = $event;
        $this->logger = $logger;
    }

    public function import(array $paths, bool $truncateBeforeImport): void
    {
        $this->logger->debug('Starting translation import.', ['paths' => $paths, 'truncateBeforeImport' => true]);

        if ($truncateBeforeImport === true) {
            $this->truncateDatabase();
        } else {
            $this->deleteNonDirtyTranslations();
        }


        $finder = (new Finder())->files()->in($paths)->name('*.ini');

        $iniFilesCount = $finder->count();
        $iniFiles = $finder->getIterator();

        $this->event->dispatch(ImportStartEvent::EVENT_NAME, new ImportStartEvent($iniFilesCount, $truncateBeforeImport));

        foreach ($iniFiles as $file) {
            $this->importFile($file);
        }

        $this->event->dispatch(ImportFinishEvent::EVENT_NAME, new ImportFinishEvent());
    }

    public function importFile(SplFileInfo $file): void
    {
        $this->logger->debug('Processing translation file.', ['path' => $file->getPathname()]);
        $this->event->dispatch(ImportAdvanceEvent::EVENT_NAME, new ImportAdvanceEvent($file));

        $content = parse_ini_file($file->getPathname(), true);

        if (empty($content)) {
            $this->logger->debug('Skipped translation file because it is empty.', ['path' => $file->getPathname()]);
            return;
        }

        $namespace = $this->getNamespaceByFile($file);
        $today = date('Y-m-d H:i:s');

        $this->connection->transactional(function () use ($content, $namespace, $today) {
            foreach ($content as $locale => $translations) {
                foreach ($translations as $name => $value) {
                    $snippet = [
                        'namespace' => $namespace,
                        'shop_uuid' => 'SWAG-CONFIG-SHOP-UUID-1',
                        'locale' => $locale,
                        'name' => $name,
                        'value' => $value,
                        'created_at' => $today
                    ];

                    try {
                        $this->logger->debug('Writing translation to database.', $snippet);
                        $this->connection->insert('s_core_snippets', $snippet);
                    } catch (DBALException $exception) {
                        $this->logger->error('Writing translation to database failed due to DBALException.', ['snippet' => $snippet, 'error' => $exception->getMessage()]);
                        // empty catch intended due unique constraint
                    }
                }
            }
        });
    }

    private function getNamespaceByFile(SplFileInfo $file): string
    {
        $namespace = $file->getRelativePathname();
        $namespace = str_replace(
            $file->getFilename(),
            pathinfo($file->getFilename(), PATHINFO_FILENAME),
            $namespace
        );

        return $namespace;
    }

    private function truncateDatabase()
    {
        $this->logger->debug('Truncating entire table because of truncateBeforeImport = true');
        $this->connection->executeQuery('TRUNCATE TABLE s_core_snippets');
    }

    private function deleteNonDirtyTranslations()
    {
        $this->logger->debug('Deleting non-dirty translations from the table.');
        $this->connection->delete('s_core_snippets', ['dirty' => 0]);
    }
}