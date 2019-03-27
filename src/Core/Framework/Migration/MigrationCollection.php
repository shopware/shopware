<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Migration;

use Shopware\Core\Framework\Migration\Exception\InvalidMigrationClassException;

class MigrationCollection
{
    /**
     * @var string[]
     */
    private $directories;

    public function __construct(array $directories)
    {
        $this->directories = $directories;
    }

    public function addDirectory(string $directory, string $namespace): void
    {
        $this->directories[$namespace] = $directory;
    }

    public function getMigrationCollection(): array
    {
        $migrations = [];

        foreach ($this->directories as $namespace => $directory) {
            foreach (scandir($directory, SCANDIR_SORT_ASCENDING) as $classFileName) {
                $path = $directory . '/' . $classFileName;
                $className = $namespace . '\\' . pathinfo($classFileName, PATHINFO_FILENAME);

                if (pathinfo($path, PATHINFO_EXTENSION) !== 'php') {
                    continue;
                }
                if (!class_exists($className)) {
                    throw new InvalidMigrationClassException($className, $path);
                }

                if (!is_a($className, MigrationStep::class, true)) {
                    continue;
                }

                $migrations[$className] = new $className();
            }
        }

        return $migrations;
    }

    /**
     * @return int[]
     */
    public function getActiveMigrationTimestamps(): array
    {
        $activeMigrations = [];

        /** @var MigrationStep $migration */
        foreach ($this->getMigrationCollection() as $migration) {
            $activeMigrations[] = $migration->getCreationTimestamp();
        }

        return $activeMigrations;
    }
}
