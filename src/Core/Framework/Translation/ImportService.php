<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Translation;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DBALException;
use Psr\Log\LoggerInterface;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Event\ImportAdvanceEvent;
use Shopware\Core\Framework\Event\ImportFinishEvent;
use Shopware\Core\Framework\Event\ImportStartEvent;
use Shopware\Core\Framework\Struct\Uuid;
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

    public function import(array $paths, bool $truncateBeforeImport, string $tenantId): void
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
            $this->importFile($file, $tenantId);
        }

        $this->event->dispatch(ImportFinishEvent::EVENT_NAME, new ImportFinishEvent());
    }

    public function importFile(SplFileInfo $file, string $tenantId): void
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

        $tenantId = hex2bin($tenantId);

        $this->connection->transactional(function () use ($content, $namespace, $today, $tenantId) {
            $touchpointId = Uuid::fromStringToBytes(Defaults::TOUCHPOINT);

            foreach ($content as $locale => $translations) {
                foreach ($translations as $name => $value) {
                    $snippet = [
                        'id' => Uuid::uuid4()->getBytes(),
                        'tenant_id' => $tenantId,
                        'namespace' => $namespace,
                        'touchpoint_id' => $touchpointId,
                        'touchpoint_tenant_id' => $tenantId,
                        'locale' => $locale,
                        'name' => $name,
                        'value' => $value,
                        'created_at' => $today,
                    ];

                    try {
                        $this->logger->debug('Writing translation to database.', $snippet);
                        $this->connection->insert('snippet', $snippet);
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
        $this->connection->executeQuery('TRUNCATE TABLE snippet');
    }

    private function deleteNonDirtyTranslations()
    {
        $this->logger->debug('Deleting non-dirty translations from the table.');
        $this->connection->delete('snippet', ['dirty' => 0]);
    }
}
