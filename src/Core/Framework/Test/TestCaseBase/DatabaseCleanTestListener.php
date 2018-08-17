<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\TestCaseBase;

use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\Test;
use PHPUnit\Framework\TestListener;
use PHPUnit\Framework\TestSuite;
use PHPUnit\Framework\Warning;
use Shopware\Core\Kernel;

/**
 * Helper class to debug data problems in the test suite
 */
class DatabaseCleanTestListener implements TestListener
{
    private $lastDataPoint = [];

    /**
     * A test ended.
     */
    public function endTest(Test $test, float $time): void
    {
        $stateResult = $this->getCurrentDbState();

        if ($this->lastDataPoint) {
            $diff = $this->createDiff($stateResult);

            if (count($diff)) {
                echo PHP_EOL . get_class($test) . PHP_EOL;
                print_r($diff);
            }
        }

        $this->lastDataPoint = $stateResult;
    }

    /**
     * An error occurred.
     */
    public function addError(Test $test, \Throwable $t, float $time): void
    {
        //nth
    }

    /**
     * A warning occurred.
     */
    public function addWarning(Test $test, Warning $e, float $time): void
    {
        //nth
    }

    /**
     * A failure occurred.
     */
    public function addFailure(Test $test, AssertionFailedError $e, float $time): void
    {
        //nth
    }

    /**
     * Incomplete test.
     */
    public function addIncompleteTest(Test $test, \Throwable $t, float $time): void
    {
        //nth
    }

    /**
     * Risky test.
     */
    public function addRiskyTest(Test $test, \Throwable $t, float $time): void
    {
        //nth
    }

    /**
     * Skipped test.
     */
    public function addSkippedTest(Test $test, \Throwable $t, float $time): void
    {
        //nth
    }

    /**
     * A test suite started.
     */
    public function startTestSuite(TestSuite $suite): void
    {
        //nth
    }

    /**
     * A test suite ended.
     */
    public function endTestSuite(TestSuite $suite): void
    {
        //nth
    }

    /**
     * A test started.
     */
    public function startTest(Test $test): void
    {
        //nth
    }

    /**
     * @return array
     */
    private function getCurrentDbState(): array
    {
        $connection = Kernel::getConnection();

        $rawTables = $connection->query('SHOW TABLES')->fetchAll();
        $stateResult = [];

        foreach ($rawTables as $nested) {
            $tableName = end($nested);
            $count = $connection->query("SELECT COUNT(*) FROM `{$tableName}`")->fetchColumn(0);

            $stateResult[$tableName] = $count;
        }

        return $stateResult;
    }

    private function createDiff(array $tableNames): array
    {
        $diff = array_diff($this->lastDataPoint, $tableNames);

        foreach (array_keys($diff) as $index) {
            $diff[$index] = $tableNames[$index] - $this->lastDataPoint[$index];

            if ($diff[$index] > 0) {
                $diff[$index] = '+' . $diff[$index];
            } else {
                $diff[$index] = '-' . $diff[$index];
            }
        }

        return $diff;
    }
}
