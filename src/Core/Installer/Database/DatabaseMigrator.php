<?php declare(strict_types=1);

namespace Shopware\Core\Installer\Database;

use Doctrine\DBAL\Connection;
use Psr\Clock\ClockInterface;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Installer\Requirements\IniConfigReader;
use Shopware\Core\Maintenance\System\Service\SetupDatabaseAdapter;

/**
 * @internal
 */
#[Package('framework')]
class DatabaseMigrator
{
    public function __construct(
        private readonly SetupDatabaseAdapter $adapter,
        private readonly MigrationCollectionFactory $migrationFactory,
        private readonly string $version,
        private readonly IniConfigReader $iniConfigReader,
        private readonly ClockInterface $clock
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

        // Use 7s as request cap so the UI stays responsive; 0/-1 mean unlimited PHP runtime.
        $configuredMaxExecutionTime = (int) $this->iniConfigReader->get('max_execution_time');
        $maxExecutionTime = $configuredMaxExecutionTime <= 0 ? 7 : min($configuredMaxExecutionTime, 7);
        $startTime = (float) $this->clock->now()->format(Defaults::MICROTIME_FORMAT);
        $executedMigrations = $offset;

        $stopped = false;
        while (iterator_count($coreMigrations->migrateInSteps(null, 1)) === 1) {
            $runningSince = (float) $this->clock->now()->format(Defaults::MICROTIME_FORMAT) - $startTime;
            ++$executedMigrations;

            // if there are more than 5 seconds execution time left, we execute more migrations in this request, otherwise we return the result
            // on first request only execute one migration, otherwise the UI will feel unresponsive
            if ($runningSince + 5 > $maxExecutionTime || $executedMigrations === 1) {
                $stopped = true;

                break;
            }
        }

        while (!$stopped && iterator_count($coreMigrations->migrateDestructiveInSteps(null, 1)) === 1) {
            $runningSince = (float) $this->clock->now()->format(Defaults::MICROTIME_FORMAT) - $startTime;
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
            'isFinished' => $coreMigrations->getExecutableDestructiveMigrations() === [],
        ];
    }
}
