<?php declare(strict_types=1);

namespace Shopware\Core\Installer\Database;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Maintenance\System\Service\SetupDatabaseAdapter;

/**
 * @internal
 */
#[Package('core')]
class DatabaseMigrator
{
    public function __construct(
        private readonly SetupDatabaseAdapter $adapter,
        private readonly MigrationCollectionFactory $migrationFactory,
        private readonly string $version
    ) {
    }

    /**
     * @return array{offset: int, total: int, isFinished: bool}
     */
    public function migrate(int $offset, Connection $connection): array
    {
        $migrationLoader = $this->migrationFactory->getMigrationCollectionLoader($connection);

        $coreMigrations = $migrationLoader->collectAllForVersion($this->version);

        if ($offset === 0) {
            $this->adapter->initializeShopwareDb($connection);

            $coreMigrations->sync();
        }

        // use 7 s as max execution time, so the UI stays responsive
        $maxExecutionTime = min(\ini_get('max_execution_time'), 7);
        $startTime = microtime(true);
        $executedMigrations = $offset;

        $stopped = false;
        while (iterator_count($coreMigrations->migrateInSteps(null, 1)) === 1) {
            $runningSince = microtime(true) - $startTime;
            ++$executedMigrations;

            // if there are more than 5 seconds execution time left, we execute more migrations in this request, otherwise we return the result
            // on first request only execute one migration, otherwise the UI will feel unresponsive
            if ($runningSince + 5 > $maxExecutionTime || $executedMigrations === 1) {
                $stopped = true;

                break;
            }
        }

        while (!$stopped && iterator_count($coreMigrations->migrateDestructiveInSteps(null, 1)) === 1) {
            $runningSince = microtime(true) - $startTime;
            ++$executedMigrations;

            // if there are more than 5 seconds execution time left, we execute more migrations in this request, otherwise we return the result
            if ($runningSince + 5 > $maxExecutionTime) {
                break;
            }
        }

        $total = $coreMigrations->getTotalMigrationCount() * 2;

        return [
            'offset' => $executedMigrations,
            'total' => $total,
            'isFinished' => \count($coreMigrations->getExecutableDestructiveMigrations()) === 0,
        ];
    }
}
