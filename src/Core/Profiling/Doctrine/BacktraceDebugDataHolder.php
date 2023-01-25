<?php declare(strict_types=1);

namespace Shopware\Core\Profiling\Doctrine;

use Shopware\Core\Framework\Log\Package;
use Symfony\Bridge\Doctrine\Middleware\Debug\DebugDataHolder;
use Symfony\Bridge\Doctrine\Middleware\Debug\Query;
use function array_slice;
use function debug_backtrace;
use const DEBUG_BACKTRACE_IGNORE_ARGS;

/**
 * @phpstan-type Backtrace array<array{function?: string, line: int, file: string, class?: string, object?: Object, type: string}>
 * @phpstan-type QueryInfo array{
 *     sql: string,
 *     executionMS: float,
 *     types: array<int|string, int>,
 *     params:  array<mixed>,
 *     backtrace?: Backtrace
 * }
 */
#[Package('core')]
class BacktraceDebugDataHolder extends DebugDataHolder
{
    /**
     * @var array<string, array<Backtrace>>
     */
    private array $backtraces = [];

    /**
     * @param array<string> $connWithBacktraces
     */
    public function __construct(private readonly array $connWithBacktraces)
    {
    }

    public function reset(): void
    {
        parent::reset();

        $this->backtraces = [];
    }

    public function addQuery(string $connectionName, Query $query): void
    {
        parent::addQuery($connectionName, $query);

        if (!\in_array($connectionName, $this->connWithBacktraces, true)) {
            return;
        }

        // array_slice to skip middleware calls in the trace
        /** @var Backtrace $withoutMiddleware */
        $withoutMiddleware = \array_slice(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS), 2);
        $this->backtraces[$connectionName][] = $withoutMiddleware;
    }

    /**
     * @return array<string, array<QueryInfo>>
     */
    public function getData(): array
    {
        $dataWithBacktraces = [];

        foreach (parent::getData() as $connectionName => $dataForConn) {
            $dataWithBacktraces[$connectionName] = $this->getDataForConnection($connectionName, $dataForConn);
        }

        return $dataWithBacktraces;
    }

    /**
     * @param array<QueryInfo> $dataForConn
     *
     * @return list<QueryInfo>
     */
    private function getDataForConnection(string $connectionName, array $dataForConn): array
    {
        $data = [];
        foreach ($dataForConn as $idx => $record) {
            $data[] = $this->addBacktracesIfAvailable($connectionName, $record, $idx);
        }

        return $data;
    }

    /**
     * @param QueryInfo $record
     *
     * @return QueryInfo
     */
    private function addBacktracesIfAvailable(string $connectionName, array $record, int $idx): array
    {
        if (!isset($this->backtraces[$connectionName])) {
            return $record;
        }

        $record['backtrace'] = $this->backtraces[$connectionName][$idx];

        return $record;
    }
}
