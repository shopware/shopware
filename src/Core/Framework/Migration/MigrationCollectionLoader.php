<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Migration;

use Doctrine\DBAL\Connection;

class MigrationCollectionLoader
{
    const CORE_MIGRATIONS = __DIR__ . '/../../Version';

    /**
     * @var string[]
     */
    private $directories = [];

    /**
     * @var Connection
     */
    private $connection;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    /**
     * @return MigrationCollectionLoader
     */
    public function addDirectory(string $directory, string $namespace): self
    {
        $this->directories[$directory] = $namespace;

        return $this;
    }

    public function syncMigrationCollection()
    {
        $migrations = [];

        MigrationRuntime::ensureMigrationTableExists($this->connection);

        foreach ($this->directories as $directory => $namespace) {
            foreach (scandir($directory) as $classFileName) {
                $path = $directory . '/' . $classFileName;
                $className = $namespace . '\\' . pathinfo($classFileName, PATHINFO_FILENAME);

                if (pathinfo($path, PATHINFO_EXTENSION) !== 'php') {
                    continue;
                }

                if (!class_exists($className)) {
                    throw new \RuntimeException('Unable to load "' . $className . '" at "' . $path . '"');
                }

                if (!is_a($className, MigrationStep::class, true)) {
                    continue;
                }

                $migrations[$className] = new $className();
            }
        }

        if (!$migrations) {
            return;
        }

        $this->addMigrationsToTable($migrations);
    }

    /**
     * @param MigrationStep[] $migrations
     */
    private function addMigrationsToTable(array $migrations)
    {
        $insertValues = [];
        foreach ($migrations as $className => $migration) {
            $insertValues[] = '('
                . $this->connection->quote($className)
                . ','
                . $this->connection->quote($migration->getCreationTimeStamp())
                . ')';
        }

        $this->connection->executeQuery(
            'INSERT IGNORE INTO `migration` (`class`, `creation_time_stamp`) VALUES '
            . implode(',', $insertValues)
        );
    }
}
