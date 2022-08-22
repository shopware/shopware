<?php declare(strict_types=1);

namespace Shopware\Core\Maintenance\System\Service;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Feature;

/**
 * @deprecated tag:v6.5.0 - Will be removed use SetupDatabaseAdapter instead
 */
class DatabaseInitializer
{
    private Connection $connection;

    private SetupDatabaseAdapter $adapter;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
        $this->adapter = new SetupDatabaseAdapter();
    }

    public function dropDatabase(string $database): void
    {
        Feature::triggerDeprecationOrThrow('v6.5.0.0', Feature::deprecatedMethodMessage(
            __CLASS__,
            __METHOD__,
            'v6.5.0.0',
            SetupDatabaseAdapter::class . '::' . __METHOD__
        ));

        $this->adapter->dropDatabase($this->connection, $database);
    }

    public function createDatabase(string $database): void
    {
        Feature::triggerDeprecationOrThrow('v6.5.0.0', Feature::deprecatedMethodMessage(
            __CLASS__,
            __METHOD__,
            'v6.5.0.0',
            SetupDatabaseAdapter::class . '::' . __METHOD__
        ));

        $this->adapter->createDatabase($this->connection, $database);
    }

    public function initializeShopwareDb(?string $database = null): bool
    {
        Feature::triggerDeprecationOrThrow('v6.5.0.0', Feature::deprecatedMethodMessage(
            __CLASS__,
            __METHOD__,
            'v6.5.0.0',
            SetupDatabaseAdapter::class . '::' . __METHOD__
        ));

        return $this->adapter->initializeShopwareDb($this->connection, $database);
    }

    public function hasShopwareTables(?string $database = null): bool
    {
        Feature::triggerDeprecationOrThrow('v6.5.0.0', Feature::deprecatedMethodMessage(
            __CLASS__,
            __METHOD__,
            'v6.5.0.0',
            SetupDatabaseAdapter::class . '::' . __METHOD__
        ));

        return $this->adapter->hasShopwareTables($this->connection, $database);
    }

    public function getTableCount(string $database): int
    {
        Feature::triggerDeprecationOrThrow('v6.5.0.0', Feature::deprecatedMethodMessage(
            __CLASS__,
            __METHOD__,
            'v6.5.0.0',
            SetupDatabaseAdapter::class . '::' . __METHOD__
        ));

        return $this->adapter->getTableCount($this->connection, $database);
    }

    /**
     * @param list<string> $ignoredSchemas
     *
     * @return list<string>
     */
    public function getExistingDatabases(array $ignoredSchemas): array
    {
        Feature::triggerDeprecationOrThrow('v6.5.0.0', Feature::deprecatedMethodMessage(
            __CLASS__,
            __METHOD__,
            'v6.5.0.0',
            SetupDatabaseAdapter::class . '::' . __METHOD__
        ));

        return $this->adapter->getExistingDatabases($this->connection, $ignoredSchemas);
    }
}
