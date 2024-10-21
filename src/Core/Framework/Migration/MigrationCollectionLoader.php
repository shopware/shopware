<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Migration;

use Doctrine\DBAL\Connection;
use Psr\Log\LoggerInterface;
use Shopware\Core\DevOps\Environment\EnvironmentHelper;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\Exception\InvalidMigrationClassException;
use Shopware\Core\Framework\Migration\Exception\UnknownMigrationSourceException;

#[Package('core')]
class MigrationCollectionLoader
{
    /**
     * Execute all migrations
     */
    final public const VERSION_SELECTION_ALL = 'all';

    /**
     * Blue-green safe:
     * - update from 6.a.* to 6.(a+1).0 -> migrations for major 6.a are NOT executed
     * - rollback from 6.(a+1).0 to 6.a.* is still possible
     * - update from 6.(a+1).0 to 6.(a+1).1 or higher -> migrations for major 6.a are executed
     * - rollback possible from 6.(a+1).1 to 6.(a+1).0 possible
     * - but rollback to 6.a.* not possible anymore!
     */
    final public const VERSION_SELECTION_BLUE_GREEN = 'blue-green';

    /**
     * Executing the migrations of the penultimate major. This should always be safe
     */
    final public const VERSION_SELECTION_SAFE = 'safe';

    final public const VALID_VERSION_SELECTION_VALUES = [
        self::VERSION_SELECTION_ALL,
        self::VERSION_SELECTION_BLUE_GREEN,
        self::VERSION_SELECTION_SAFE,
    ];

    /**
     * @deprecated tag:v6.7.0 - Will be removed. Use VALID_VERSION_SELECTION_VALUES instead
     */
    public const VALID_VERSION_SELECTION_SAFE_VALUES = self::VALID_VERSION_SELECTION_VALUES;

    private const SW_MAJOR_VERSION_WHICH_INTRODUCED_MIGRATION_NAMESPACES = 3;
    private const BEFORE_PREVIOUS_MAJOR_VERSION_SUBTRAHEND = 2;
    private const FIRST_MINOR_VERSION = 1;

    /**
     * @var array<string, MigrationSource>
     */
    private array $migrationSources = [];

    /**
     * @internal
     *
     * @param iterable<MigrationSource> $migrationSources
     */
    public function __construct(
        private readonly Connection $connection,
        private readonly MigrationRuntime $migrationRuntime,
        iterable $migrationSources = [],
        private readonly ?LoggerInterface $logger = null
    ) {
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
            throw MigrationException::unknownMigrationSource($name);
        }

        $source = $this->migrationSources[$name];

        return new MigrationCollection($source, $this->migrationRuntime, $this->connection, $this->logger);
    }

    /**
     * @param self::VERSION_SELECTION_* $mode
     */
    public function collectAllForVersion(string $version, string $mode = self::VERSION_SELECTION_ALL): MigrationCollection
    {
        $safeMajorVersion = $this->getLastSafeMajorVersion($version, $mode);

        $namespaces = [];
        for ($major = self::SW_MAJOR_VERSION_WHICH_INTRODUCED_MIGRATION_NAMESPACES;
            $safeMajorVersion >= self::SW_MAJOR_VERSION_WHICH_INTRODUCED_MIGRATION_NAMESPACES && $major <= $safeMajorVersion;
            ++$major
        ) {
            $namespaces[] = $this->getSource('core.V6_' . $major);
        }
        $namespaces[] = $this->getSource('core');

        $source = new MigrationSource('allForVersion', $namespaces);

        return new MigrationCollection($source, $this->migrationRuntime, $this->connection, $this->logger);
    }

    /**
     * @param self::VERSION_SELECTION_* $mode
     */
    public function getLastSafeMajorVersion(string $currentVersion, string $mode = self::VERSION_SELECTION_ALL): int
    {
        if (!\in_array($mode, self::VALID_VERSION_SELECTION_VALUES, true)) {
            throw MigrationException::invalidVersionSelectionMode($mode);
        }

        [, $safeMajorVersion, $currentMinor] = explode('.', $currentVersion);
        $safeMajorVersion = (int) $safeMajorVersion;

        $simulateMajor = EnvironmentHelper::getVariable('FEATURE_ALL') === 'major';
        if ($simulateMajor) {
            ++$safeMajorVersion;
        }

        if ($mode === self::VERSION_SELECTION_SAFE) {
            return $safeMajorVersion - self::BEFORE_PREVIOUS_MAJOR_VERSION_SUBTRAHEND;
        }

        if ($mode === self::VERSION_SELECTION_BLUE_GREEN) {
            --$safeMajorVersion;
            if ($currentMinor < self::FIRST_MINOR_VERSION) {
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
     * @return array<string, MigrationCollection>
     */
    public function collectAll(): array
    {
        $collections = [];

        foreach ($this->migrationSources as $source) {
            $sourceName = $source->getName();
            $collections[$sourceName] = $this->collect($sourceName);
        }

        return $collections;
    }

    private function getSource(string $name): MigrationSource
    {
        if (!isset($this->migrationSources[$name])) {
            throw MigrationException::unknownMigrationSource($name);
        }

        return $this->migrationSources[$name];
    }
}
