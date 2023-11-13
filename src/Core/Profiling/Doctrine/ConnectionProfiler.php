<?php declare(strict_types=1);

namespace Shopware\Core\Profiling\Doctrine;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver\Middleware as MiddlewareInterface;
use Doctrine\DBAL\Types\ConversionException;
use Doctrine\DBAL\Types\Type;
use Shopware\Core\Framework\Log\Package;
use Symfony\Bridge\Doctrine\DataCollector\ObjectParameter;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\DataCollector\DataCollector;
use Symfony\Component\HttpKernel\DataCollector\LateDataCollectorInterface;
use Symfony\Component\VarDumper\Cloner\Data;

/**
 * @internal
 *
 * @phpstan-import-type Backtrace from BacktraceDebugDataHolder
 * @phpstan-import-type QueryInfo from BacktraceDebugDataHolder
 *
 * @phpstan-type SanitizedQueryInfo array{sql: string, executionMS: float, types: array<(int | string), int>, params: Data, runnable: bool, explainable: bool, backtrace?: Backtrace}
 * @phpstan-type SanitizedQueryInfoGroup array{sql: string, executionMS: float, types: array<(int | string), int>, params: Data, runnable: bool, explainable: bool, backtrace?: Backtrace, count: int, index: int, executionPercent?: float}
 */
#[Package('core')]
class ConnectionProfiler extends DataCollector implements LateDataCollectorInterface
{
    private ?BacktraceDebugDataHolder $dataHolder = null;

    /**
     * @var ?array<string, array<string, SanitizedQueryInfoGroup>>
     */
    private ?array $groupedQueries = null;

    /**
     * @var array<string>
     */
    private array $connections = ['default'];

    /**
     * @internal
     */
    public function __construct(private readonly Connection $connection)
    {
        $profilingMiddleware = current(array_filter(
            $this->connection->getConfiguration()->getMiddlewares(),
            fn (MiddlewareInterface $middleware) => $middleware instanceof ProfilingMiddleware
        ));

        if ($profilingMiddleware === false) {
            return;
        }

        $this->dataHolder = $profilingMiddleware->debugDataHolder;
    }

    public function getName(): string
    {
        return 'app.connection_collector';
    }

    public function reset(): void
    {
        $this->data = [
            'queries' => [],
            'connections' => $this->connections,
        ];
        if ($this->dataHolder === null) {
            return;
        }

        $this->dataHolder->reset();
    }

    /**
     * @return array<string>
     */
    public function getConnections(): array
    {
        return $this->data['connections'];
    }

    public function getQueryCount(): int
    {
        return array_sum(array_map('count', $this->data['queries']));
    }

    /**
     * @return array<string, array<int, SanitizedQueryInfo>>
     */
    public function getQueries(): array
    {
        return $this->data['queries'];
    }

    public function getTime(): float
    {
        $time = 0;
        foreach ($this->data['queries'] as $queries) {
            foreach ($queries as $query) {
                $time += $query['executionMS'];
            }
        }

        return $time;
    }

    public function collect(Request $request, Response $response, ?\Throwable $exception = null): void
    {
        // noop
    }

    public function lateCollect(): void
    {
        if ($this->dataHolder === null) {
            $this->data['queries'] = [];
            $this->data['connections'] = $this->connections;

            return;
        }

        $this->data = ['queries' => $this->collectQueries(), 'connections' => $this->connections];
        $this->groupedQueries = null;

        $this->dataHolder->reset();
    }

    /**
     * @return array<string, array<string, SanitizedQueryInfoGroup>>
     */
    public function getGroupedQueries(): array
    {
        if ($this->groupedQueries !== null) {
            return $this->groupedQueries;
        }

        $this->groupedQueries = [];
        $totalExecutionMS = 0;
        foreach ($this->getQueries() as $connection => $queries) {
            $connectionGroupedQueries = [];
            foreach ($queries as $i => $query) {
                $key = $query['sql'];
                if (!isset($connectionGroupedQueries[$key])) {
                    $connectionGroupedQueries[$key] = $query;
                    $connectionGroupedQueries[$key]['executionMS'] = 0;
                    $connectionGroupedQueries[$key]['count'] = 0;
                    $connectionGroupedQueries[$key]['index'] = $i; // "Explain query" relies on query index in 'queries'.
                }

                $connectionGroupedQueries[$key]['executionMS'] += $query['executionMS'];
                ++$connectionGroupedQueries[$key]['count'];
                $totalExecutionMS += $query['executionMS'];
            }

            usort($connectionGroupedQueries, static fn ($a, $b) => $b['executionMS'] <=> $a['executionMS']);
            $this->groupedQueries[$connection] = $connectionGroupedQueries;
        }

        foreach ($this->groupedQueries as $connection => $queries) {
            foreach ($queries as $i => $query) {
                $this->groupedQueries[$connection][$i]['executionPercent']
                    = $this->executionTimePercentage($query['executionMS'], $totalExecutionMS);
            }
        }

        return $this->groupedQueries;
    }

    public function getGroupedQueryCount(): int
    {
        return array_sum(
            array_map(
                fn (array $connectionGroupedQueries) => \count($connectionGroupedQueries),
                $this->getGroupedQueries()
            )
        );
    }

    /**
     * @return array<string, array<int, SanitizedQueryInfo>>
     */
    private function collectQueries(): array
    {
        if ($this->dataHolder === null) {
            return [];
        }

        $queries = [];

        foreach ($this->dataHolder->getData() as $connection => $connectionQueries) {
            $queries[$connection] = $this->sanitizeQueries($connectionQueries);
        }

        return $queries;
    }

    /**
     * @param array<QueryInfo> $queries
     *
     * @return array<SanitizedQueryInfo>
     */
    private function sanitizeQueries(array $queries): array
    {
        return array_map(fn (array $query) => $this->sanitizeQuery($query), $queries);
    }

    /**
     * @param QueryInfo $query
     *
     * @return SanitizedQueryInfo
     */
    private function sanitizeQuery(array $query): array
    {
        $query['explainable'] = true;
        $query['runnable'] = true;
        $query['params'] ??= [];
        if (!\is_array($query['params'])) {
            $query['params'] = [$query['params']];
        }
        if (!\is_array($query['types'])) {
            $query['types'] = [];
        }
        foreach ($query['params'] as $j => $param) {
            $e = null;
            if (isset($query['types'][$j])) {
                // Transform the param according to the type
                $type = $query['types'][$j];
                if (\is_string($type)) {
                    $type = Type::getType($type);
                }
                if ($type instanceof Type) {
                    $query['types'][$j] = $type->getBindingType();

                    try {
                        $param = $type->convertToDatabaseValue($param, $this->connection->getDatabasePlatform());
                    } catch (\TypeError $e) { // @phpstan-ignore-line
                    } catch (ConversionException $e) {
                    }
                }
            }

            [$query['params'][$j], $explainable, $runnable] = $this->sanitizeParam($param, $e);
            if (!$explainable) {
                $query['explainable'] = false;
            }

            if (!$runnable) {
                $query['runnable'] = false;
            }
        }

        $query['params'] = $this->cloneVar($query['params']);

        return $query;
    }

    /**
     * Sanitizes a param.
     *
     * The return value is an array with the sanitized value and a boolean
     * indicating if the original value was kept (allowing to use the sanitized value to explain the query).
     *
     * @return array{0: mixed, 1: bool, 2: bool}
     */
    private function sanitizeParam(mixed $var, ?\Throwable $error): array
    {
        if (\is_object($var)) {
            return [$o = new ObjectParameter($var, $error), false, $o->isStringable() && !$error];
        }

        if ($error) {
            return ['⚠ ' . $error->getMessage(), false, false];
        }

        if (\is_array($var)) {
            $a = [];
            $explainable = $runnable = true;
            foreach ($var as $k => $v) {
                [$value, $e, $r] = $this->sanitizeParam($v, null);
                $explainable = $explainable && $e;
                $runnable = $runnable && $r;
                $a[$k] = $value;
            }

            return [$a, $explainable, $runnable];
        }

        if (\is_resource($var)) {
            return [sprintf('/* Resource(%s) */', get_resource_type($var)), false, false];
        }

        return [$var, true, true];
    }

    private function executionTimePercentage(float $executionTimeMS, float $totalExecutionTimeMS): float
    {
        if (!$totalExecutionTimeMS) {
            return 0;
        }

        return $executionTimeMS / $totalExecutionTimeMS * 100;
    }
}
