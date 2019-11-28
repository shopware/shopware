<?php declare(strict_types=1);

namespace Shopware\Recovery\Common\Steps;

use Shopware\Core\Framework\Migration\MigrationCollectionLoader;
use Shopware\Recovery\Common\MigrationRuntime;

class MigrationStep
{
    public const UPDATE = 'update';
    public const UPDATE_DESTRUCTIVE = 'update_destructive';

    /**
     * @var MigrationRuntime
     */
    private $migrationManager;

    /**
     * @var MigrationCollectionLoader
     */
    private $migrationCollectionLoader;
    /**
     * @var array
     */
    private $identifiers;

    public function __construct(MigrationRuntime $migrationManager, MigrationCollectionLoader $migrationCollectionLoader, array $identifiers)
    {
        $this->migrationManager = $migrationManager;
        $this->migrationCollectionLoader = $migrationCollectionLoader;
        $this->identifiers = $identifiers;
    }

    /**
     * @return ErrorResult|FinishResult|ValidResult
     */
    public function run(string $modus, int $offset, int $totalCount = null)
    {
        if ($offset === 0) {
            foreach ($this->identifiers as $identifier) {
                $this->migrationCollectionLoader->syncMigrationCollection($identifier);
            }
        }

        if (!$totalCount) {
            if ($modus === self::UPDATE) {
                $totalCount = \count($this->migrationManager->getExecutableMigrations(null, null, $this->identifiers));
            } else {
                $totalCount = \count($this->migrationManager->getExecutableDestructiveMigrations(null, null, $this->identifiers));
            }
        }

        try {
            if ($modus === self::UPDATE) {
                $result = $this->migrationManager->migrate(null, 1, $this->identifiers);
            } else {
                $result = $this->migrationManager->migrateDestructive(null, 1, $this->identifiers);
            }

            $executedMigration = iterator_count($result) === 1;

            if (!$executedMigration) {
                return new FinishResult($offset, $totalCount);
            }
        } catch (\Throwable $e) {
            return new ErrorResult($e->getMessage(), $e);
        }

        return new ValidResult($offset + 1, $totalCount);
    }
}
