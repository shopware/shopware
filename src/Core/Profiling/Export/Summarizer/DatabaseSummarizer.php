<?php declare(strict_types=1);

namespace Shopware\Core\Profiling\Export\Summarizer;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\Hasher;
use Shopware\Core\Profiling\Doctrine\ConnectionProfiler;
use Symfony\Component\HttpKernel\DataCollector\DataCollectorInterface;
use Symfony\Component\VarDumper\Cloner\Data;

/**
 * @internal
 *
 * @phpstan-type Statement array{sql: string, connection: string, count: int, totalMs: float, maxMs: float, paramSets: array<string, true>, callers: array<string, int>}
 */
#[Package('framework')]
class DatabaseSummarizer implements PanelSummarizer
{
    private const SLOWEST = 15;

    private const MOST_REPEATED = 10;

    private const CALLERS = 3;

    private const INTERNAL_NAMESPACES = [
        'Doctrine\\',
        'Symfony\\Bridge\\Doctrine\\',
        'Shopware\\Core\\Framework\\DataAbstractionLayer\\',
        'Shopware\\Core\\Framework\\Telemetry\\Doctrine\\',
        'Shopware\\Core\\Profiling\\',
        'Shopware\\Core\\System\\SalesChannel\\Entity\\',
        'Shopware\\Elasticsearch\\Admin\\AdminElasticsearchEntitySearcher',
        'Shopware\\Elasticsearch\\Framework\\DataAbstractionLayer\\',
    ];

    public function supports(DataCollectorInterface $collector): bool
    {
        return $collector instanceof ConnectionProfiler;
    }

    public function summarize(DataCollectorInterface $collector, mixed $data): array
    {
        \assert($collector instanceof ConnectionProfiler);

        /** @var array<string, Statement> $statements */
        $statements = [];
        $queryCount = 0;
        $totalMs = 0.0;

        foreach ($collector->getQueries() as $connection => $queries) {
            foreach ($queries as $query) {
                $ms = $query['executionMS'] * 1000;
                $sql = (string) preg_replace(['/\s+/', '/\?(?:\s*,\s*\?)+/'], [' ', '?…'], trim($query['sql']));
                $key = $connection . "\0" . $sql;
                $statements[$key] ??= ['sql' => $sql, 'connection' => (string) $connection, 'count' => 0, 'totalMs' => 0.0, 'maxMs' => 0.0, 'paramSets' => [], 'callers' => []];

                $statement = &$statements[$key];
                ++$statement['count'];
                $statement['totalMs'] += $ms;
                $statement['maxMs'] = max($statement['maxMs'], $ms);
                $statement['paramSets'][Hasher::hash(serialize($this->params($query['params'])))] = true;

                $caller = $this->caller($query['backtrace'] ?? []);
                if ($caller !== null) {
                    $statement['callers'][$caller] = ($statement['callers'][$caller] ?? 0) + 1;
                }
                unset($statement);

                ++$queryCount;
                $totalMs += $ms;
            }
        }

        $identicalRuns = array_sum(array_map(static fn (array $statement): int => $statement['count'] - \count($statement['paramSets']), $statements));

        return [
            'queries' => $queryCount,
            'timeMs' => round($totalMs, 2),
            'statements' => \count($statements),
            'identicalRuns' => $identicalRuns,
            'top' => array_map($this->format(...), $this->top($statements)),
        ];
    }

    /**
     * @param array<string, Statement> $statements
     *
     * @return list<Statement>
     */
    private function top(array $statements): array
    {
        $byTime = $statements;
        uasort($byTime, static fn (array $a, array $b): int => $b['totalMs'] <=> $a['totalMs']);

        $repeated = array_filter($statements, static fn (array $statement): bool => $statement['count'] > 1);
        uasort($repeated, static fn (array $a, array $b): int => $b['count'] <=> $a['count']);

        $top = \array_slice($byTime, 0, self::SLOWEST, true) + \array_slice($repeated, 0, self::MOST_REPEATED, true);
        uasort($top, static fn (array $a, array $b): int => $b['totalMs'] <=> $a['totalMs']);

        return array_values($top);
    }

    /**
     * @param Statement $statement
     *
     * @return array<string, mixed>
     */
    private function format(array $statement): array
    {
        arsort($statement['callers']);

        $formatted = [
            'sql' => $statement['sql'],
            'count' => $statement['count'],
            'totalMs' => round($statement['totalMs'], 2),
            'maxMs' => round($statement['maxMs'], 2),
            'identicalRuns' => $statement['count'] - \count($statement['paramSets']),
        ];

        if ($statement['callers'] !== []) {
            $formatted['callers'] = \array_slice($statement['callers'], 0, self::CALLERS, true);
        }

        if ($statement['connection'] !== 'default') {
            $formatted['connection'] = $statement['connection'];
        }

        return $formatted;
    }

    private function params(mixed $params): mixed
    {
        return $params instanceof Data ? $params->getValue(true) : $params;
    }

    /**
     * @param array<int, array<string, mixed>> $backtrace
     */
    private function caller(array $backtrace): ?string
    {
        foreach ($backtrace as $index => $frame) {
            $class = $frame['class'] ?? null;
            if (!\is_string($class) || $this->isInternal($class)) {
                continue;
            }

            $location = $backtrace[$index - 1] ?? $frame;
            $file = \is_string($location['file'] ?? null) ? basename($location['file']) : '';
            $line = \is_int($location['line'] ?? null) ? $location['line'] : 0;

            return \sprintf('%s::%s (%s:%d)', $class, \is_string($frame['function'] ?? null) ? $frame['function'] : '', $file, $line);
        }

        return null;
    }

    private function isInternal(string $class): bool
    {
        foreach (self::INTERNAL_NAMESPACES as $namespace) {
            if (str_starts_with($class, $namespace)) {
                return true;
            }
        }

        return false;
    }
}
