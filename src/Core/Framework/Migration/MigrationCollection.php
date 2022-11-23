<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Migration;

use Doctrine\DBAL\Connection;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Doctrine\MultiInsertQueryQueue;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Migration\Exception\InvalidMigrationClassException;

/**
 * @package core
 */
class MigrationCollection
{
    /**
     * @var array<class-string<MigrationStep>, MigrationStep>|null
     */
    private ?array $migrationSteps = null;

    private MigrationSource $migrationSource;

    private Connection $connection;

    private MigrationRuntime $migrationRuntime;

    private ?LoggerInterface $logger = null;

    /**
     * @internal
     */
    public function __construct(
        MigrationSource $migrationSource,
        MigrationRuntime $migrationRuntime,
        Connection $connection,
        ?LoggerInterface $logger = null
    ) {
        $this->migrationSource = $migrationSource;
        $this->connection = $connection;
        $this->migrationRuntime = $migrationRuntime;
        $this->logger = $logger;
    }

    public function getName(): string
    {
        return $this->migrationSource->getName();
    }

    public function sync(): void
    {
        $insertQuery = new MultiInsertQueryQueue($this->connection, 250, true);

        foreach ($this->getMigrationSteps() as $className => $migrationStep) {
            $insertQuery->addInsert('migration', $this->getMigrationData($className, $migrationStep));
        }

        $insertQuery->execute();
    }

    public function migrateInSteps(?int $until = null, ?int $limit = null): \Generator
    {
        return $this->migrationRuntime->migrate($this->migrationSource, $until, $limit);
    }

    /**
     * @return list<class-string<MigrationStep>>
     */
    public function migrateInPlace(?int $until = null, ?int $limit = null): array
    {
        return iterator_to_array($this->migrateInSteps($until, $limit));
    }

    public function migrateDestructiveInSteps(?int $until = null, ?int $limit = null): \Generator
    {
        return $this->migrationRuntime->migrateDestructive($this->migrationSource, $until, $limit);
    }

    /**
     * @return list<class-string<MigrationStep>>
     */
    public function migrateDestructiveInPlace(?int $until = null, ?int $limit = null): array
    {
        return iterator_to_array($this->migrateDestructiveInSteps($until, $limit));
    }

    /**
     * @return list<class-string<MigrationStep>>
     */
    public function getExecutableMigrations(?int $until = null, ?int $limit = null): array
    {
        return $this->migrationRuntime->getExecutableMigrations($this->migrationSource, $until, $limit);
    }

    /**
     * @return list<class-string<MigrationStep>>
     */
    public function getExecutableDestructiveMigrations(?int $until = null, ?int $limit = null): array
    {
        return $this->migrationRuntime->getExecutableDestructiveMigrations($this->migrationSource, $until, $limit);
    }

    public function getTotalMigrationCount(?int $until = null, ?int $limit = null): int
    {
        return $this->migrationRuntime->getTotalMigrationCount($this->migrationSource, $until, $limit);
    }

    /**
     * @return array<class-string<MigrationStep>, MigrationStep>
     */
    public function getMigrationSteps(): array
    {
        $this->ensureStepsLoaded();

        return $this->migrationSteps ?? [];
    }

    /**
     * @return list<int>
     */
    public function getActiveMigrationTimestamps(): array
    {
        $activeMigrations = [];

        foreach ($this->getMigrationSteps() as $migration) {
            $activeMigrations[] = $migration->getCreationTimestamp();
        }

        return $activeMigrations;
    }

    /**
     * @param class-string<MigrationStep> $className
     *
     * @return array{class: class-string<MigrationStep>, creation_timestamp: int, update?: string, update_destructive?: string, message?: string}
     */
    private function getMigrationData(string $className, MigrationStep $migrationStep): array
    {
        $default = [
            'class' => $className,
            'creation_timestamp' => $migrationStep->getCreationTimestamp(),
        ];

        /** @var array{class: class-string<MigrationStep>, creation_timestamp: int, update?: string, update_destructive?: string, message?: string} $default */
        Feature::callSilentIfInactive('v6.6.0.0', function () use ($className, &$default): void {
            $oldName = $this->migrationSource->mapToOldName($className);
            if ($oldName === null) {
                return;
            }

            /** @var false|array{class: class-string<MigrationStep>, creation_timestamp: int, update: string, update_destructive: string, message: string} $row */
            $row = $this->connection->fetchAssociative(
                'SELECT * FROM migration WHERE class = :class',
                ['class' => $oldName]
            );

            if ($row === false) {
                return;
            }

            $row['class'] = $className;

            $default = $row;
        });

        return $default;
    }

    private function ensureStepsLoaded(): void
    {
        if ($this->migrationSteps !== null) {
            return;
        }

        $this->migrationSteps = [];
        foreach ($this->loadMigrationSteps() as $name => $step) {
            $this->migrationSteps[$name] = $step;
        }
    }

    /**
     * @throws InvalidMigrationClassException
     *
     * @return array<class-string<MigrationStep>, MigrationStep>
     */
    private function loadMigrationSteps(): array
    {
        $migrations = [];

        foreach ($this->migrationSource->getSourceDirectories() as $directory => $namespace) {
            if (!is_readable($directory)) {
                if ($this->logger !== null) {
                    $this->logger->warning(
                        'Migration directory "{directory}" for namespace "{namespace}" does not exist or is not readable.',
                        [
                            'directory' => $directory,
                            'namespace' => $namespace,
                        ]
                    );
                }

                continue;
            }

            $classFiles = scandir($directory, \SCANDIR_SORT_ASCENDING);
            if (!$classFiles) {
                continue;
            }

            foreach ($classFiles as $classFileName) {
                $path = $directory . '/' . $classFileName;
                $className = $namespace . '\\' . pathinfo($classFileName, \PATHINFO_FILENAME);

                if (pathinfo($path, \PATHINFO_EXTENSION) !== 'php') {
                    continue;
                }

                if (!class_exists($className) && !trait_exists($className) && !interface_exists($className)) {
                    throw new InvalidMigrationClassException($className, $path);
                }

                if (!is_subclass_of($className, MigrationStep::class, true)) {
                    continue;
                }

                $migrations[$className] = new $className();
            }
        }

        return $migrations;
    }
}
