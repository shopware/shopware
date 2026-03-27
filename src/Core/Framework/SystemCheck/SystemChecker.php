<?php declare(strict_types=1);

namespace Shopware\Core\Framework\SystemCheck;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\SystemCheck\Check\Category;
use Shopware\Core\Framework\SystemCheck\Check\Result;
use Shopware\Core\Framework\SystemCheck\Check\Status;
use Shopware\Core\Framework\SystemCheck\Check\SystemCheckExecutionContext;

/**
 * @internal
 */
#[Package('framework')]
class SystemChecker
{
    /**
     * @param iterable<int, BaseCheck> $checks
     *
     * @internal
     */
    public function __construct(private readonly iterable $checks)
    {
    }

    /**
     * @return list<Result>
     */
    public function check(SystemCheckExecutionContext $context): array
    {
        [$allowedChecks, $disallowedChecks] = $this->groupByPermissionToRun($context);

        $results = $this->runChecksByCategory($this->groupByCategory($allowedChecks));
        $skippedChecksResults = $this->skipChecks($disallowedChecks, \sprintf('Check not allowed to run in this execution context: %s', $context->name));

        return array_merge($results, $skippedChecksResults);
    }

    /**
     * @param array<int, list<BaseCheck>> $categoryCheckCluster
     *
     * @return list<Result>
     */
    private function runChecksByCategory(array $categoryCheckCluster): array
    {
        $results = [];
        $shouldStop = false;

        foreach ($categoryCheckCluster as $category => $checks) {
            if ($shouldStop) {
                $results = array_merge($results, $this->skipChecks($checks, 'Check is not run due to previous failed checks.'));
                continue;
            }

            $checksResult = $this->runChecks($checks);
            $results = array_merge($results, $checksResult);
            if ($this->shouldStopRunning($checksResult, $category)) {
                $shouldStop = true;
            }
        }

        return $results;
    }

    /**
     * @param list<BaseCheck> $checks
     *
     * @return list<Result>
     */
    private function runChecks(array $checks): array
    {
        return array_map(static function (BaseCheck $check) {
            try {
                return $check->run();
            } catch (\Throwable $e) {
                return new Result($check->name(), Status::FAILURE, \sprintf('Failed to run the check with ERROR: %s', $e->getMessage()));
            }
        }, $checks);
    }

    /**
     * @param list<BaseCheck> $checks
     *
     * @return list<Result>
     */
    private function skipChecks(array $checks, string $message): array
    {
        return array_map(
            static fn (BaseCheck $check) => new Result($check->name(), Status::SKIPPED, $message),
            $checks
        );
    }

    /**
     * @param list<BaseCheck> $checks
     *
     * @return array<int, list<BaseCheck>>
     */
    private function groupByCategory(array $checks): array
    {
        $categoryCheckCluster = [];
        foreach ($checks as $check) {
            $categoryCheckCluster[$check->category()->value][] = $check;
        }
        ksort($categoryCheckCluster);

        return $categoryCheckCluster;
    }

    /**
     * having a non-healthy check in core category should stop running further checks as it would effect everything else.
     *
     * @param list<Result> $results
     */
    private function shouldStopRunning(array $results, int $category): bool
    {
        if ($category !== Category::SYSTEM->value) {
            return false;
        }

        foreach ($results as $result) {
            if (!$result->healthy) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array{list<BaseCheck>, list<BaseCheck>} segregated checks by permission to run
     */
    private function groupByPermissionToRun(SystemCheckExecutionContext $context): array
    {
        $allowedChecks = [];
        $disallowedChecks = [];
        foreach ($this->checks as $check) {
            if ($check->allowedToRunIn($context)) {
                $allowedChecks[] = $check;
            } else {
                $disallowedChecks[] = $check;
            }
        }

        return [$allowedChecks, $disallowedChecks];
    }
}
