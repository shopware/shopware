<?php

namespace Shopware\Core\Framework\Api\HealthCheck\Service;

use Shopware\Core\Framework\Api\HealthCheck\Model\Result;

class Manager
{
    /**
     * @param iterable<Check> $checks
     */
    public function __construct(private readonly iterable $checks)
    {
    }

    /**
     * @return array<Result>
     */
    public function healthCheck(): array
    {
        $priorityCheckCluster = $this->groupChecksByPriority();

       return $this->runChecks($priorityCheckCluster);
    }

    private function runChecks(array $priorityCheckCluster): array
    {
        $results = [];
        foreach ($priorityCheckCluster as $checks) {
            $result = $this->doRunChecks($checks);
            $results = array_merge($results, $result);

            if ($this->shouldStopPropagation($result)) {
                break;
            }
        }

        return $results;
    }

    /**
     * @param Check[] $checks
     * @return array<Result>
     */
    private function doRunChecks(array $checks): array
    {
        $results = [];
        foreach ($checks as $check) {
            $results[] = $check->run();
        }

        return $results;
    }

    /**
     * @return array<int, Check[]>
     */
    private function groupChecksByPriority(): array
    {
        $priorityCheckCluster = [];
        foreach ($this->checks as $check) {
            $priorityCheckCluster[$check->priority()][] = $check;
        }
        ksort($priorityCheckCluster);

        return $priorityCheckCluster;
    }

    private function shouldStopPropagation(array $results): bool
    {
        foreach ($results as $result) {
            if (! $result->healthy()) {
                return true;
            }
        }

        return false;
    }
}
