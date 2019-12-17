<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\Exception\InvalidMigrationClassException;
use Shopware\Core\Framework\Migration\Exception\UnknownMigrationSourceException;

class MigrationCollectionLoader
{
    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var array<string, MigrationSource>
     */
    private $migrationSources;

    /**
     * @var MigrationRuntime
     */
    private $migrationRuntime;

    public function __construct(Connection $connection, MigrationRuntime $migrationRuntime, iterable $migrationSources = [])
    {
        $this->connection = $connection;
        $this->migrationRuntime = $migrationRuntime;

        foreach ($migrationSources as $migrationSource) {
            $this->addSource($migrationSource);
        }
    }

    public function addSource(MigrationSource $migrationSource): void
    {
        $this->migrationSources[$migrationSource->getName()] = $migrationSource;
    }

    /**
     * @throws UnknownMigrationSourceException
     * @throws InvalidMigrationClassException
     */
    public function collect(string $name): MigrationCollection
    {
        if (!isset($this->migrationSources[$name])) {
            throw new UnknownMigrationSourceException($name);
        }

        $source = $this->migrationSources[$name];

        return new MigrationCollection($source, $this->migrationRuntime, $this->connection);
    }

    /**
     * @throws InvalidMigrationClassException
     * @throws UnknownMigrationSourceException
     *
     * @return MigrationCollection[]
     */
    public function collectAll(): array
    {
        $collections = [];

        foreach ($this->migrationSources as $source) {
            $collections[$source->getName()] = $this->collect($source->getName());
        }

        return $collections;
    }
}
