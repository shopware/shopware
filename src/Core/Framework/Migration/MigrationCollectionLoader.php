<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Doctrine\MultiInsertQueryQueue;
use Shopware\Core\Framework\Migration\Exception\InvalidMigrationClassException;

class MigrationCollectionLoader
{
    public const CORE_MIGRATIONS = __DIR__ . '/../../Migration';

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

    public function syncMigrationCollection(): void
    {
        $migrations = [];

        foreach ($this->directories as $directory => $namespace) {
            foreach (scandir($directory, SCANDIR_SORT_ASCENDING) as $classFileName) {
                $path = $directory . '/' . $classFileName;
                $className = $namespace . '\\' . pathinfo($classFileName, PATHINFO_FILENAME);

                if (pathinfo($path, PATHINFO_EXTENSION) !== 'php') {
                    continue;
                }

                if (!class_exists($className)) {
                    throw new InvalidMigrationClassException('Unable to load "' . $className . '" at "' . $path . '"');
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
    private function addMigrationsToTable(array $migrations): void
    {
        $insertQuery = new MultiInsertQueryQueue($this->connection, 250, true);
        foreach ($migrations as $className => $migration) {
            $insertQuery->addInsert('migration', [
                '`class`' => $className,
                '`creation_timestamp`' => $migration->getCreationTimestamp(),
            ]);
        }
        $insertQuery->execute();
    }
}
