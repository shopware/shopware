<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\TestCaseBase;

use PHPUnit\Runner\AfterTestHook;
use PHPUnit\Runner\BeforeTestHook;
use Shopware\Core\Kernel;

/**
 * @internal
 * Helper class to debug data problems in the test suite
 */
class DatabaseCleanTestExtension implements BeforeTestHook, AfterTestHook
{
    private const IGNORED = [
        'version_commit',
        'version_commit_data',
    ];

    /**
     * @var array<mixed>
     */
    private array $lastDataPoint = [];

    public function executeBeforeTest(string $test): void
    {
        if (!$this->lastDataPoint) {
            $this->lastDataPoint = $this->getCurrentDbState();
        }
    }

    public function executeAfterTest(string $test, float $time): void
    {
        $stateResult = $this->getCurrentDbState();

        if ($this->lastDataPoint) {
            $diff = $this->createDiff($stateResult);

            if (!empty($diff)) {
                echo \PHP_EOL . $test . \PHP_EOL;
                print_r($diff);
            }
        }

        $this->lastDataPoint = $stateResult;
    }

    /**
     * @return array<string, int>
     */
    private function getCurrentDbState(): array
    {
        $connection = Kernel::getConnection();

        $rawTables = $connection->fetchAllAssociative('SHOW TABLES');
        $stateResult = [];

        foreach ($rawTables as $nested) {
            $tableName = end($nested);
            $count = $connection->fetchOne('SELECT COUNT(*) FROM ' . $tableName);

            $stateResult[(string) $tableName] = (int) $count;
        }

        return $stateResult;
    }

    /**
     * @param array<mixed> $state
     *
     * @return array<mixed>
     */
    private function createDiff(array $state): array
    {
        $diff = [];
        $addedTables = array_diff(array_keys($state), array_keys($this->lastDataPoint));
        if ($addedTables) {
            $diff['added'] = $addedTables;
        }
        $deletedTables = array_diff(array_keys($this->lastDataPoint), array_keys($state));
        if ($deletedTables) {
            $diff['deleted'] = $deletedTables;
        }

        $commonTables = array_intersect(array_keys($state), array_keys($this->lastDataPoint));
        $changed = [];
        foreach ($commonTables as $table) {
            $countDiff = $state[$table] - $this->lastDataPoint[$table];
            if ($countDiff > 0 && !\in_array($table, self::IGNORED, true)) {
                $changed[$table] = $countDiff;
            }
        }
        if ($changed) {
            $diff['changed'] = $changed;
        }

        return $diff;
    }
}
