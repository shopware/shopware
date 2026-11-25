<?php declare(strict_types=1);

namespace Shopware\Core\Framework\SystemCheck;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\SystemCheck\Check\Category;
use Shopware\Core\Framework\SystemCheck\Check\Result;
use Shopware\Core\Framework\SystemCheck\Check\Status;
use Shopware\Core\Framework\SystemCheck\Check\SystemCheckExecutionContext;
use Shopware\Core\Framework\SystemCheck\Message\RunSystemChecks;
use Symfony\Component\Messenger\MessageBusInterface;

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
    public function __construct(
        private readonly iterable $checks,
        private readonly SystemCheckResultStorage $resultStorage,
        private readonly MessageBusInterface $messageBus,
    ) {
    }

    /**
     * Runs all checks, or only the check with the given name, in the background.
     */
    public function queueCheck(?string $name = null): void
    {
        $names = array_map(static fn (BaseCheck $check) => $check->name(), [...$this->checks]);
        if ($name !== null && !\in_array($name, $names, true)) {
            throw SystemCheckException::unknownCheck($name);
        }

        $this->messageBus->dispatch(new RunSystemChecks($name));
    }

    /**
     * Runs every check that is allowed in at least one of the given contexts, or only the check with the given name.
     *
     * @param SystemCheckExecutionContext|list<SystemCheckExecutionContext> $contexts
     *
     * @return array<Result>
     */
    public function check(SystemCheckExecutionContext|array $contexts, ?string $name = null): array
    {
        $contexts = \is_array($contexts) ? $contexts : [$contexts];
        [$allowedChecks, $disallowedChecks] = $this->groupByPermissionToRun($contexts, $name);

        $results = $this->runChecksByCategory($this->groupByCategory($allowedChecks));
        $skippedChecksResults = $this->skipChecks($disallowedChecks, \sprintf(
            'Check not allowed to run in this execution context: %s',
            implode(', ', array_map(static fn (SystemCheckExecutionContext $context) => $context->name, $contexts))
        ));

        $this->resultStorage->save($results);

        return array_merge($results, $skippedChecksResults);
    }

    /**
     * @param array<int, array<BaseCheck>> $categoryCheckCluster
     *
     * @return array<Result>
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
     * @param array<BaseCheck> $checks
     *
     * @return array<Result>
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
     * @param array<BaseCheck> $checks
     *
     * @return array<Result>
     */
    private function skipChecks(array $checks, string $message): array
    {
        return array_map(
            static fn (BaseCheck $check) => new Result($check->name(), Status::SKIPPED, $message),
            $checks
        );
    }

    /**
     * @param array<BaseCheck> $checks
     *
     * @return array<int, array<BaseCheck>>
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
     * @param array<Result> $results
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
     * @param list<SystemCheckExecutionContext> $contexts
     *
     * @return array{array<BaseCheck>, array<BaseCheck>} segregated checks by permission to run
     */
    private function groupByPermissionToRun(array $contexts, ?string $name): array
    {
        $allowedChecks = [];
        $disallowedChecks = [];
        foreach ($this->checks as $check) {
            if ($name !== null && $check->name() !== $name) {
                continue;
            }

            if ($this->allowedToRunInAny($check, $contexts)) {
                $allowedChecks[] = $check;
            } else {
                $disallowedChecks[] = $check;
            }
        }

        return [$allowedChecks, $disallowedChecks];
    }

    /**
     * @param list<SystemCheckExecutionContext> $contexts
     */
    private function allowedToRunInAny(BaseCheck $check, array $contexts): bool
    {
        foreach ($contexts as $context) {
            if ($check->allowedToRunIn($context)) {
                return true;
            }
        }

        return false;
    }
}
