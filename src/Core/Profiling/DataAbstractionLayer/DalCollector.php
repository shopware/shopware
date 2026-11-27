<?php declare(strict_types=1);

namespace Shopware\Core\Profiling\DataAbstractionLayer;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver\Middleware;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Context\SalesChannelApiSource;
use Shopware\Core\Framework\Api\Sync\SyncOperation;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DataAbstractionLayerException;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Aggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\AggregationResultCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\Filter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Grouping\FieldGrouping;
use Shopware\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Parser\QueryStringParser;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Query\ScoreQuery;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteResult;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\Hasher;
use Shopware\Core\Profiling\Doctrine\BacktraceDebugDataHolder;
use Shopware\Core\Profiling\Doctrine\ProfilingMiddleware;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\DataCollector\DataCollector;
use Symfony\Component\HttpKernel\DataCollector\LateDataCollectorInterface;
use Symfony\Component\VarDumper\Cloner\Data;

/**
 * @internal
 *
 * @phpstan-import-type Frame from CallSiteResolver
 *
 * @phpstan-type Summary array{criteria: array<string, mixed>, shape: array<array-key, mixed>|null, fingerprint: mixed}
 * @phpstan-type Outcome array{count: ?int, total: ?int, backend: string, affected: array<string, int>}
 * @phpstan-type RequestPositions array<int, array<string, int>>
 * @phpstan-type LoggedRequest array{source: string, url: string, time: float, body: Data, position: int}
 * @phpstan-type Operation array{
 *     operation: string,
 *     entity: string,
 *     depth: int,
 *     criteria: array<string, mixed>,
 *     shape: string,
 *     fingerprint: string,
 *     context: array<string, mixed>,
 *     trace: list<Frame>,
 *     duration: float,
 *     queries: array{int, int},
 *     requests: array{RequestPositions, RequestPositions},
 *     count: ?int,
 *     total: ?int,
 *     backend: string,
 *     affected: array<string, int>,
 *     error: ?string,
 * }
 * @phpstan-type CallOperation array{operation: string, duration: float, count: ?int, total: ?int, backend: string, queries: list<int>, requests: list<LoggedRequest>, criteria: Data}
 * @phpstan-type Call array{
 *     entity: string,
 *     shape: string,
 *     fingerprint: string,
 *     method: string,
 *     caller: ?string,
 *     file: ?string,
 *     line: ?int,
 *     depth: int,
 *     trace: list<Frame>,
 *     context: array<string, mixed>,
 *     criteria: Data,
 *     duration: float,
 *     count: ?int,
 *     total: ?int,
 *     backend: string,
 *     queries: list<int>,
 *     queryCount: int,
 *     affected: array<string, int>,
 *     operations: list<CallOperation>,
 *     write: bool,
 *     repeated: bool,
 *     warnings: list<string>,
 *     error: ?string,
 * }
 * @phpstan-type RepeatedCall array{entity: string, method: string, write: bool, count: int, duplicates: int, duration: float, callers: list<Frame>, calls: list<int>, criteria: Data}
 */
#[Package('framework')]
class DalCollector extends DataCollector implements LateDataCollectorInterface
{
    public const OPERATION_READ = 'read';
    public const OPERATION_SEARCH_IDS = 'searchIds';
    public const OPERATION_AGGREGATE = 'aggregate';
    public const OPERATION_UPSERT = 'upsert';
    public const OPERATION_INSERT = 'insert';
    public const OPERATION_UPDATE = 'update';
    public const OPERATION_DELETE = 'delete';
    public const OPERATION_SYNC = 'sync';

    public const WARNING_SLOW = 'slow';
    public const WARNING_LARGE_READ = 'large-read';
    public const WARNING_WRITE_IN_SAFE_REQUEST = 'write-in-safe-request';

    private const SLOW_CALL_MS = 100.0;

    private const LARGE_READ_ROWS = 200;

    private const WRITE_STEP = 3;

    private const STEP_ORDER = [
        self::OPERATION_AGGREGATE => 0,
        self::OPERATION_SEARCH_IDS => 1,
        self::OPERATION_READ => 2,
        self::OPERATION_UPSERT => self::WRITE_STEP,
        self::OPERATION_INSERT => self::WRITE_STEP,
        self::OPERATION_UPDATE => self::WRITE_STEP,
        self::OPERATION_DELETE => self::WRITE_STEP,
        self::OPERATION_SYNC => self::WRITE_STEP,
    ];

    private const MAX_OPERATIONS = 2000;

    private const MAX_LIST_ITEMS = 10;

    private const CONNECTION = 'default';

    private const ELASTICSEARCH_RESULT_STATE = 'loaded-by-elastic';

    private const BACKEND_SQL = 'sql';

    private const BACKEND_ELASTICSEARCH = 'elasticsearch';

    private const MASKED_KEYS = ['ids', 'term', 'value', 'parameters'];

    private ?BacktraceDebugDataHolder $dataHolder = null;

    /**
     * @var array<int, Operation>
     */
    private array $operations = [];

    private int $started = 0;

    private int $skipped = 0;

    private int $depth = 0;

    private bool $safeRequest = false;

    /**
     * @var list<DalRequestLog>
     */
    private readonly array $requestLogs;

    /**
     * @param iterable<DalRequestLog> $requestLogs
     */
    public function __construct(
        Connection $connection,
        iterable $requestLogs = [],
        private readonly CallSiteResolver $callSiteResolver = new CallSiteResolver(),
    ) {
        $this->requestLogs = iterator_to_array($requestLogs, false);

        $profilingMiddleware = current(array_filter(
            $connection->getConfiguration()->getMiddlewares(),
            static fn (Middleware $middleware) => $middleware instanceof ProfilingMiddleware
        ));

        if ($profilingMiddleware instanceof ProfilingMiddleware) {
            $this->dataHolder = $profilingMiddleware->debugDataHolder;
        }
    }

    /**
     * @template TResult of EntityCollection<covariant Entity>|IdSearchResult|AggregationResultCollection
     *
     * @param self::OPERATION_READ|self::OPERATION_SEARCH_IDS|self::OPERATION_AGGREGATE $operation
     * @param \Closure(): TResult $call
     *
     * @return TResult
     */
    public function traceRead(string $operation, EntityDefinition $definition, Criteria $criteria, Context $context, \Closure $call): EntityCollection|IdSearchResult|AggregationResultCollection
    {
        return $this->record(
            $operation,
            $definition->getEntityName(),
            $context,
            function () use ($criteria): array {
                $summary = $this->summarizeCriteria($criteria);

                return ['criteria' => $summary, 'shape' => $this->mask($summary), 'fingerprint' => $summary];
            },
            $call,
            $this->describeRead(...),
        );
    }

    /**
     * @template TResult of array<string, list<EntityWriteResult>>|WriteResult
     *
     * @param self::OPERATION_UPSERT|self::OPERATION_INSERT|self::OPERATION_UPDATE|self::OPERATION_DELETE $operation
     * @param array<array<string, mixed>> $rawData
     * @param \Closure(): TResult $call
     *
     * @return TResult
     */
    public function traceWrite(string $operation, EntityDefinition $definition, array $rawData, WriteContext $writeContext, \Closure $call): array|WriteResult
    {
        $entity = $definition->getEntityName();

        return $this->record(
            $operation,
            $entity,
            $writeContext->getContext(),
            static fn (): array => [
                'criteria' => [
                    'rows' => \count($rawData),
                    'fields' => array_values(array_unique(array_merge(...array_map(array_keys(...), array_values($rawData))))),
                ],
                'shape' => null,
                'fingerprint' => $rawData,
            ],
            $call,
            fn (array|WriteResult $result): array => $this->describeWrite($result, $entity),
        );
    }

    /**
     * @param list<SyncOperation> $operations
     * @param \Closure(): WriteResult $call
     */
    public function traceSync(array $operations, WriteContext $writeContext, \Closure $call): WriteResult
    {
        $entities = array_unique(array_map(static fn (SyncOperation $operation): string => $operation->getEntity(), $operations));

        return $this->record(
            self::OPERATION_SYNC,
            implode(', ', $entities),
            $writeContext->getContext(),
            static fn (): array => [
                'criteria' => [
                    'operations' => array_map(
                        static fn (SyncOperation $operation): string => \sprintf('%s %s (%d rows)', $operation->getAction(), $operation->getEntity(), \count($operation->getPayload())),
                        $operations
                    ),
                ],
                'shape' => null,
                'fingerprint' => $operations,
            ],
            $call,
            fn (WriteResult $result): array => $this->describeWrite($result, null),
        );
    }

    public function getName(): string
    {
        return 'dal';
    }

    public function collect(Request $request, Response $response, ?\Throwable $exception = null): void
    {
        $this->safeRequest = $request->isMethodSafe();
    }

    public function lateCollect(): void
    {
        ksort($this->operations);

        $calls = $this->buildCalls();
        $repeated = $this->findRepeatedCalls($calls);

        foreach ($repeated as $group) {
            foreach ($group['calls'] as $callIndex) {
                $calls[$callIndex]['repeated'] = true;
            }
        }

        $this->data = [
            'enabled' => $this->dataHolder !== null,
            'calls' => $calls,
            'repeated' => $repeated,
            'warnings' => array_count_values(array_merge(...array_column($calls, 'warnings'))),
            'operationCount' => \count($this->operations),
            'skipped' => $this->skipped,
            'time' => array_sum(array_map(
                static fn (array $operation): float => $operation['depth'] === 0 ? $operation['duration'] : 0.0,
                $this->operations
            )),
        ];
    }

    public function reset(): void
    {
        $this->data = [];
        $this->operations = [];
        $this->started = 0;
        $this->skipped = 0;
        $this->depth = 0;
        $this->safeRequest = false;
    }

    public function isEnabled(): bool
    {
        return $this->data['enabled'] ?? false;
    }

    /**
     * @return list<Call>
     */
    public function getCalls(): array
    {
        return $this->data['calls'] ?? [];
    }

    public function getCallCount(): int
    {
        return \count($this->getCalls());
    }

    /**
     * @return list<RepeatedCall>
     */
    public function getRepeatedCalls(): array
    {
        return $this->data['repeated'] ?? [];
    }

    /**
     * @return array<string, int>
     */
    public function getWarningCounts(): array
    {
        return $this->data['warnings'] ?? [];
    }

    public function getOperationCount(): int
    {
        return $this->data['operationCount'] ?? 0;
    }

    public function getSkippedCount(): int
    {
        return $this->data['skipped'] ?? 0;
    }

    /**
     * @return array<string, int>
     */
    public function getQueryCountsBySource(): array
    {
        $counts = [self::BACKEND_SQL => 0];
        foreach ($this->getCalls() as $call) {
            foreach ($call['operations'] as $step) {
                $counts[self::BACKEND_SQL] += \count($step['queries']);
                foreach ($step['requests'] as $request) {
                    $counts[$request['source']] = ($counts[$request['source']] ?? 0) + 1;
                }
            }
        }

        return $counts;
    }

    public function getTime(): float
    {
        return $this->data['time'] ?? 0.0;
    }

    /**
     * @return list<Call>
     */
    private function buildCalls(): array
    {
        $groups = [];
        $keys = [];
        $lastSteps = [];
        $open = [];

        foreach ($this->operations as $index => $operation) {
            $depth = $operation['depth'];
            $key = serialize([$operation['entity'], $operation['trace'][0] ?? null, $depth]);
            $group = $open[$depth] ?? null;

            if ($group === null || $keys[$group] !== $key || self::STEP_ORDER[$lastSteps[$group]] >= self::STEP_ORDER[$operation['operation']]) {
                $group = \count($groups);
                $groups[$group] = [];
                $keys[$group] = $key;
            }

            $groups[$group][] = $index;
            $lastSteps[$group] = $operation['operation'];
            $open = array_filter($open, static fn (int $openDepth): bool => $openDepth < $depth, \ARRAY_FILTER_USE_KEY);
            $open[$depth] = $group;
        }

        $queries = $this->assignQueries();
        $requests = $this->assignRequests();

        return array_values(array_map(fn (array $indexes): array => $this->buildCall($indexes, $queries, $requests), $groups));
    }

    /**
     * @param non-empty-list<int> $indexes
     * @param array<int, list<int>> $queries
     * @param array<int, list<array{int, string, int}>> $requests
     *
     * @return Call
     */
    private function buildCall(array $indexes, array $queries, array $requests): array
    {
        $steps = array_map(fn (int $index): array => [
            'operation' => $this->operations[$index]['operation'],
            'duration' => $this->operations[$index]['duration'],
            'count' => $this->operations[$index]['count'],
            'total' => $this->operations[$index]['total'],
            'backend' => $this->operations[$index]['backend'],
            'queries' => $queries[$index] ?? [],
            'requests' => array_map(fn (array $request): array => $this->describeRequest(...$request), $requests[$index] ?? []),
            'criteria' => $this->cloneVar($this->operations[$index]['criteria']),
        ], $indexes);

        $first = $this->operations[$indexes[0]];
        $entry = $first['trace'][0] ?? null;
        $totals = array_filter(array_column($steps, 'total'), static fn (?int $total): bool => $total !== null);

        $last = $steps[\count($steps) - 1];
        $duration = array_sum(array_column($steps, 'duration'));
        $isWriteCall = self::STEP_ORDER[$first['operation']] === self::WRITE_STEP;
        $readRows = $last['operation'] === self::OPERATION_READ ? $last['count'] : null;
        $hasWriteStep = array_filter($steps, static fn (array $step): bool => self::STEP_ORDER[$step['operation']] === self::WRITE_STEP) !== [];

        $affected = [];
        foreach ($indexes as $index) {
            foreach ($this->operations[$index]['affected'] as $name => $rows) {
                $affected[$name] = ($affected[$name] ?? 0) + $rows;
            }
        }

        return [
            'entity' => $first['entity'],
            'shape' => $first['shape'],
            'fingerprint' => $first['fingerprint'],
            'method' => $entry['call'] ?? $first['operation'],
            'caller' => $first['trace'][1]['call'] ?? null,
            'file' => $entry['file'] ?? null,
            'line' => $entry['line'] ?? null,
            'depth' => $first['depth'],
            'trace' => $first['trace'],
            'context' => $first['context'],
            'criteria' => $steps[0]['criteria'],
            'duration' => $duration,
            'count' => $last['count'],
            'total' => $totals === [] ? null : end($totals),
            'backend' => \in_array(self::BACKEND_ELASTICSEARCH, array_column($steps, 'backend'), true) ? self::BACKEND_ELASTICSEARCH : self::BACKEND_SQL,
            'queries' => array_merge(...array_column($steps, 'queries')),
            'queryCount' => array_sum(array_map(static fn (array $step): int => \count($step['queries']) + \count($step['requests']), $steps)),
            'affected' => $affected,
            'operations' => $steps,
            'write' => $isWriteCall,
            'repeated' => false,
            'warnings' => $this->warnings($hasWriteStep, $duration, $readRows, $first['criteria']),
            'error' => $this->operations[$indexes[\count($indexes) - 1]]['error'],
        ];
    }

    /**
     * @param array<string, mixed> $criteria
     *
     * @return list<string>
     */
    private function warnings(bool $hasWriteStep, float $duration, ?int $readRows, array $criteria): array
    {
        $warnings = [];

        if ($duration >= self::SLOW_CALL_MS) {
            $warnings[] = self::WARNING_SLOW;
        }

        if ($readRows !== null && $readRows >= self::LARGE_READ_ROWS && !isset($criteria['limit'])) {
            $warnings[] = self::WARNING_LARGE_READ;
        }

        if ($hasWriteStep && $this->safeRequest) {
            $warnings[] = self::WARNING_WRITE_IN_SAFE_REQUEST;
        }

        return $warnings;
    }

    /**
     * @param list<Call> $calls
     *
     * @return list<RepeatedCall>
     */
    private function findRepeatedCalls(array $calls): array
    {
        $groups = [];
        foreach ($calls as $index => $call) {
            $groups[$call['entity'] . '|' . $call['method'] . '|' . $call['shape']][] = $index;
        }

        $repeated = [];
        foreach ($groups as $indexes) {
            if (\count($indexes) < 2) {
                continue;
            }

            $callers = [];
            $fingerprints = [];
            $duration = 0.0;
            foreach ($indexes as $index) {
                $call = $calls[$index];
                $callers[$call['file'] . ':' . $call['line']] = ['call' => $call['caller'] ?? $call['method'], 'file' => $call['file'], 'line' => $call['line']];
                $fingerprints[$call['fingerprint']] = true;
                $duration += $call['duration'];
            }

            $first = $calls[$indexes[0]];
            $repeated[] = [
                'entity' => $first['entity'],
                'method' => $first['method'],
                'write' => $first['write'],
                'count' => \count($indexes),
                'duplicates' => \count($indexes) - \count($fingerprints),
                'duration' => $duration,
                'callers' => array_values($callers),
                'calls' => $indexes,
                'criteria' => $first['criteria'],
            ];
        }

        usort($repeated, static fn (array $a, array $b): int => [$b['count'], $b['duration']] <=> [$a['count'], $a['duration']]);

        return $repeated;
    }

    /**
     * @return array<int, list<int>>
     */
    private function assignQueries(): array
    {
        $owners = [];
        foreach ($this->operations as $index => $operation) {
            [$from, $to] = $operation['queries'];
            for ($query = $from; $query < $to; ++$query) {
                $owners[$query] = $index;
            }
        }

        ksort($owners);

        $queries = [];
        foreach ($owners as $query => $index) {
            $queries[$index][] = $query;
        }

        return $queries;
    }

    /**
     * @return array<int, list<array{int, string, int}>>
     */
    private function assignRequests(): array
    {
        $owners = [];
        foreach ($this->operations as $index => $operation) {
            [$from, $to] = $operation['requests'];
            foreach ($to as $log => $channels) {
                foreach ($channels as $channel => $end) {
                    for ($request = $from[$log][$channel] ?? 0; $request < $end; ++$request) {
                        $owners[$log . '|' . $channel . '|' . $request] = [$index, $log, $channel, $request];
                    }
                }
            }
        }

        $requests = [];
        foreach ($owners as [$index, $log, $channel, $request]) {
            $requests[$index][] = [$log, $channel, $request];
        }

        return $requests;
    }

    /**
     * @return RequestPositions
     */
    private function requestPositions(): array
    {
        return array_map(static fn (DalRequestLog $log): array => $log->getRequestCounts(), $this->requestLogs);
    }

    /**
     * @return LoggedRequest
     */
    private function describeRequest(int $log, string $channel, int $index): array
    {
        $request = $this->requestLogs[$log]->getRequest($channel, $index);

        return [
            'source' => $this->requestLogs[$log]->getName(),
            'url' => $request['url'],
            'time' => $request['time'],
            'body' => $this->cloneVar($request['body']),
            'position' => $request['position'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function summarizeCriteria(Criteria $criteria): array
    {
        return array_filter([
            'title' => $criteria->getTitle(),
            'ids' => $criteria->getIds(),
            'term' => $criteria->getTerm(),
            'filter' => array_map($this->summarizeFilter(...), $criteria->getFilters()),
            'post-filter' => array_map($this->summarizeFilter(...), $criteria->getPostFilters()),
            'query' => array_map(
                fn (ScoreQuery $query): array => ['score' => $query->getScore(), 'query' => $this->summarizeFilter($query->getQuery())],
                $criteria->getQueries()
            ),
            'sort' => array_map(
                static fn (FieldSorting $sorting): string => $sorting->getField() . ' ' . $sorting->getDirection(),
                $criteria->getSorting()
            ),
            'grouping' => array_map(static fn (FieldGrouping $grouping): string => $grouping->getField(), $criteria->getGroupFields()),
            'aggregations' => array_map(
                static fn (Aggregation $aggregation): string => \sprintf('%s(%s)', self::shortName($aggregation), implode(', ', $aggregation->getFields())),
                $criteria->getAggregations()
            ),
            'associations' => array_map($this->summarizeCriteria(...), $criteria->getAssociations()),
            'fields' => $criteria->getFields(),
            'excluded-fields' => $criteria->getExcludedFields(),
            'limit' => $criteria->getLimit(),
            'offset' => $criteria->getOffset(),
            'total-count-mode' => $criteria->getTotalCountMode(),
        ], static fn (mixed $value): bool => $value !== null && $value !== [] && $value !== 0);
    }

    /**
     * @return array<string, mixed>
     */
    private function summarizeFilter(Filter $filter): array
    {
        try {
            return QueryStringParser::toArray($filter);
        } catch (DataAbstractionLayerException) {
            return ['type' => self::shortName($filter), 'fields' => $filter->getFields()];
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function summarizeContext(Context $context): array
    {
        $source = $context->getSource();

        return array_filter([
            'languages' => $context->getLanguageIdChain(),
            'version' => $context->getVersionId() === Defaults::LIVE_VERSION ? 'live' : $context->getVersionId(),
            'scope' => $context->getScope(),
            'source' => self::shortName($source),
            'salesChannel' => $source instanceof SalesChannelApiSource ? $source->getSalesChannelId() : null,
            'inheritance' => $context->considerInheritance(),
        ], static fn (mixed $value): bool => $value !== null);
    }

    /**
     * @template TResult
     *
     * @param \Closure(): Summary $summarize
     * @param \Closure(): TResult $call
     * @param \Closure(TResult): Outcome $describe
     *
     * @return TResult
     */
    private function record(string $operation, string $entity, Context $context, \Closure $summarize, \Closure $call, \Closure $describe): mixed
    {
        $dataHolder = $this->dataHolder;

        if ($dataHolder === null) {
            return $call();
        }

        if ($this->started >= self::MAX_OPERATIONS) {
            ++$this->skipped;

            return $call();
        }

        $index = $this->started++;
        $depth = $this->depth;
        $summary = $summarize();
        $contextSummary = $this->summarizeContext($context);
        $trace = $this->callSiteResolver->resolve(debug_backtrace(\DEBUG_BACKTRACE_IGNORE_ARGS));
        $firstQuery = $dataHolder->getQueryCount(self::CONNECTION);
        $firstRequests = $this->requestPositions();

        ++$this->depth;
        $start = hrtime(true);

        /** @param Outcome $outcome */
        $store = function (array $outcome, ?string $error) use ($index, $operation, $entity, $depth, $summary, $contextSummary, $trace, $start, $firstQuery, $firstRequests, $dataHolder): void {
            $this->depth = $depth;
            $this->operations[$index] = [
                'operation' => $operation,
                'entity' => $entity,
                'depth' => $depth,
                'criteria' => $this->truncate($summary['criteria']),
                'shape' => $this->hash($summary['shape'] ?? [$trace[0] ?? null]),
                'fingerprint' => $this->hash([$summary['fingerprint'], $contextSummary]),
                'context' => $contextSummary,
                'trace' => $trace,
                'duration' => (hrtime(true) - $start) / 1e6,
                'queries' => [$firstQuery, $dataHolder->getQueryCount(self::CONNECTION)],
                'requests' => [$firstRequests, $this->requestPositions()],
                ...$outcome,
                'error' => $error,
            ];
        };

        try {
            $result = $call();
        } catch (\Throwable $exception) {
            $store(['count' => null, 'total' => null, 'backend' => self::BACKEND_SQL, 'affected' => []], $exception::class);

            throw $exception;
        }

        $store($describe($result), null);

        return $result;
    }

    /**
     * @param EntityCollection<covariant Entity>|IdSearchResult|AggregationResultCollection $result
     *
     * @return Outcome
     */
    private function describeRead(EntityCollection|IdSearchResult|AggregationResultCollection $result): array
    {
        if ($result instanceof EntityCollection) {
            return ['count' => $result->count(), 'total' => null, 'backend' => self::BACKEND_SQL, 'affected' => []];
        }

        $backend = $result->hasState(self::ELASTICSEARCH_RESULT_STATE) ? self::BACKEND_ELASTICSEARCH : self::BACKEND_SQL;

        if ($result instanceof IdSearchResult) {
            return ['count' => \count($result->getIds()), 'total' => $result->getTotal(), 'backend' => $backend, 'affected' => []];
        }

        return ['count' => $result->count(), 'total' => null, 'backend' => $backend, 'affected' => []];
    }

    /**
     * @param array<string, list<EntityWriteResult>>|WriteResult $result
     *
     * @return Outcome
     */
    private function describeWrite(array|WriteResult $result, ?string $entity): array
    {
        $affected = [];
        foreach ($result instanceof WriteResult ? [$result->getWritten(), $result->getDeleted()] : [$result] as $results) {
            foreach ($results as $name => $rows) {
                $affected[$name] = ($affected[$name] ?? 0) + \count($rows);
            }
        }

        return [
            'count' => $entity === null ? array_sum($affected) : ($affected[$entity] ?? 0),
            'total' => null,
            'backend' => self::BACKEND_SQL,
            'affected' => $affected,
        ];
    }

    /**
     * @param array<array-key, mixed> $value
     *
     * @return array<array-key, mixed>
     */
    private function mask(array $value): array
    {
        foreach ($value as $key => $item) {
            if (\in_array($key, self::MASKED_KEYS, true)) {
                $value[$key] = '?';
            } elseif (\is_array($item)) {
                $value[$key] = $this->mask($item);
            }
        }

        return $value;
    }

    /**
     * @param array<array-key, mixed> $value
     *
     * @return array<array-key, mixed>
     */
    private function truncate(array $value): array
    {
        if (array_is_list($value) && \count($value) > self::MAX_LIST_ITEMS) {
            $value = [
                ...\array_slice($value, 0, self::MAX_LIST_ITEMS),
                \sprintf('… %d more', \count($value) - self::MAX_LIST_ITEMS),
            ];
        }

        return array_map(fn (mixed $item): mixed => \is_array($item) ? $this->truncate($item) : $item, $value);
    }

    /**
     * @param array<array-key, mixed> $value
     */
    private function hash(array $value): string
    {
        return Hasher::hash(serialize($value));
    }

    private static function shortName(object $object): string
    {
        $class = $object::class;
        $separator = strrpos($class, '\\');

        return $separator === false ? $class : substr($class, $separator + 1);
    }
}
