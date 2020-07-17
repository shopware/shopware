<?php declare(strict_types=1);

namespace Shopware\Recovery\Common\Steps;

use Shopware\Core\Framework\Migration\MigrationCollection;

class MigrationStep
{
    public const UPDATE = 'update';
    public const UPDATE_DESTRUCTIVE = 'update_destructive';

    /**
     * @var MigrationCollection
     */
    private $collection;

    public function __construct(MigrationCollection $collection)
    {
        $this->collection = $collection;
    }

    /**
     * @return ErrorResult|FinishResult|ValidResult
     */
    public function run(string $modus, int $offset, ?int $totalCount = null)
    {
        if ($offset === 0) {
            $this->collection->sync();
        }

        if (!$totalCount) {
            if ($modus === self::UPDATE) {
                $totalCount = \count($this->collection->getExecutableMigrations());
            } else {
                $totalCount = \count($this->collection->getExecutableDestructiveMigrations());
            }
        }

        try {
            if ($modus === self::UPDATE) {
                $result = $this->collection->migrateInSteps(null, 1);
            } else {
                $result = $this->collection->migrateDestructiveInSteps(null, 1);
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
