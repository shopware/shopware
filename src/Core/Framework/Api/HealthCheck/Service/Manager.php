<?php

namespace Shopware\Core\Framework\Api\HealthCheck\Service;

use Shopware\Core\Framework\Api\HealthCheck\Model\Result;
use Shopware\Core\Framework\Api\HealthCheck\Model\Status;
use SplStack;

class Manager
{
    private array $checksMap;

    /**
     * @param iterable<Check> $checks
     */
    public function __construct(private readonly iterable $checks)
    {
        $this->checksMap = [];
        foreach ($checks as $check) {
            $this->checksMap[get_class($check)] = $check;
        }
    }

    /**
     * @return array<Result>
     */
    public function healthCheck(): array
    {
        $graph = $this->getDependencyGraph();

        $traverseResults = $this->dfsResults($graph);
        // skip deadlocked dependencies..
        foreach ($this->checksMap as $check) {
            if (!isset($traverseResults[get_class($check)])) {
                $traverseResults[get_class($check)] = new Result(
                    get_class($check),
                    Status::SKIPPED,
                    'Deadlocked dependency'
                );
            }
        }

        return array_values($traverseResults);
    }

    public function dfsResults(array $dependencyGraph): array
    {
        $sortedDependencyGraph = $this->getSortedDependencyGraph($dependencyGraph);
        $stack = new SplStack();
        foreach ($sortedDependencyGraph as $check) {
            $stack->push($check);
        }

        $visited = [];
        $results = [];
        while (! $stack->isEmpty()) {
            $check = $stack->pop();
            if (isset($visited[$check])) {
                continue;
            }

            $visited[$check] = true;
            $dependencies = $this->checksMap[$check]->dependsOn();
            $results[$check] = $this->runCheck($dependencies, $results, $check);
        }

        return $results;
    }

    private function getDependencyGraph(): array
    {
        $dependencyGraph = [];
        foreach ($this->checks as $check) {
            $dependencyGraph[get_class($check)] = $check->dependsOn();
        }

        return $dependencyGraph;
    }

    /**
     * @param array $dependencyGraph
     * @return array
     */
    private function getSortedDependencyGraph(array $dependencyGraph): array
    {
        $sortedDependencyGraph = $dependencyGraph;
        uasort($sortedDependencyGraph, function (array $a, array $b) {
            return count($a) - count($b);
        });

        return array_keys($sortedDependencyGraph);
    }

    /**
     * @param $dependencies
     * @param array $results
     * @param string $check
     * @return array
     */
    private function runCheck($dependencies, array $results, string $check): Result
    {
        foreach ($dependencies as $dependency) {
            if (! empty($results[$dependency]) && $results[$dependency]->healthy()) {
                return new Result($check, Status::SKIPPED, 'Dependency check failed');
            }
        }

        return $this->checksMap[$check]->run();
    }

}
