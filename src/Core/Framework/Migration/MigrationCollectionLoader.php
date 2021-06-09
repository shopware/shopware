<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Migration;

use Doctrine\DBAL\Connection;
use Psr\Log\LoggerInterface;
use Shopware\Core\DevOps\Environment\EnvironmentHelper;
use Shopware\Core\Framework\Migration\Exception\InvalidMigrationClassException;
use Shopware\Core\Framework\Migration\Exception\UnknownMigrationSourceException;

class MigrationCollectionLoader
{
    /**
     * Execute all migrations
     */
    public const VERSION_SELECTION_ALL = 'all';

    /**
     * Blue-green safe:
     * - update from 6.a.* to 6.(a+1).0 -> migrations for major 6.a are NOT executed
     * - rollback from 6.(a+1).0 to 6.a.* is still possible
     * - update from 6.(a+1).0 to 6.(a+1).1 or higher -> migrations for major 6.a are executed
     * - rollback possible from 6.(a+1).1 to 6.(a+1).0 possible
     * - but rollback to 6.a.* not possible anymore!
     */
    public const VERSION_SELECTION_BLUE_GREEN = 'blue-green';

    /**
     * Executing the migrations of the penultimate major. This should always be safe
     */
    public const VERSION_SELECTION_SAFE = 'safe';

    private const VALID_VERSION_SELECTION_SAFE_VALUES = [
        self::VERSION_SELECTION_ALL,
        self::VERSION_SELECTION_BLUE_GREEN,
        self::VERSION_SELECTION_SAFE,
    ];

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

    /**
     * @var LoggerInterface|null
     */
    private $logger;

    public function __construct(Connection $connection, MigrationRuntime $migrationRuntime, iterable $migrationSources = [], ?LoggerInterface $logger = null)
    {
        $this->connection = $connection;
        $this->migrationRuntime = $migrationRuntime;

        foreach ($migrationSources as $migrationSource) {
            $this->addSource($migrationSource);
        }
        $this->logger = $logger;
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

        return new MigrationCollection($source, $this->migrationRuntime, $this->connection, $this->logger);
    }

    public function collectAllForVersion(string $version, string $mode = self::VERSION_SELECTION_ALL): MigrationCollection
    {
        $safeMajorVersion = $this->getLastSafeMajorVersion($version, $mode);

        $namespaces = [];
        for ($major = 3; $safeMajorVersion >= 3 && $major <= $safeMajorVersion; ++$major) {
            $namespaces[] = $this->getSource('core.V6_' . $major);
        }
        $namespaces[] = $this->getSource('core');

        $source = new MigrationSource('allForVersion', $namespaces);

        return new MigrationCollection($source, $this->migrationRuntime, $this->connection, $this->logger);
    }

    public function getLastSafeMajorVersion(string $currentVersion, string $mode = self::VERSION_SELECTION_ALL): int
    {
        if (!\in_array($mode, self::VALID_VERSION_SELECTION_SAFE_VALUES, true)) {
            throw new \RuntimeException(sprintf(
                'mode needs to be one of these values: "%s"',
                implode('", "', self::VALID_VERSION_SELECTION_SAFE_VALUES)
            ));
        }

        [$_, $safeMajorVersion, $currentMinor] = explode('.', $currentVersion);
        $safeMajorVersion = (int) $safeMajorVersion;

        $simulateMajor = EnvironmentHelper::getVariable('FEATURE_ALL') === 'major';
        if ($simulateMajor) {
            ++$safeMajorVersion;
        }

        if ($mode === self::VERSION_SELECTION_SAFE) {
            return $safeMajorVersion - 2;
        }

        if ($mode === self::VERSION_SELECTION_BLUE_GREEN) {
            --$safeMajorVersion;
            if ($currentMinor < 1) {
                --$safeMajorVersion;
            }

            return $safeMajorVersion;
        }

        return $safeMajorVersion;
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

    private function getSource(string $name): MigrationSource
    {
        if (!isset($this->migrationSources[$name])) {
            throw new UnknownMigrationSourceException($name);
        }

        return $this->migrationSources[$name];
    }
}
