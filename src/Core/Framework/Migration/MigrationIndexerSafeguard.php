<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Migration;

use Shopware\Core\Framework\Log\Package;
use Shopware\Tests\DevOps\Core\Migration\MigrationIndexerSafeguardTest;
use Symfony\Component\Finder\Finder;

/**
 * Static-analysis safeguard that flags migrations writing to tables feeding a
 * DAL indexer without scheduling the corresponding re-index via
 * {@see MigrationStep::registerIndexer()}.
 *
 * Consumers drive the check with a per-indexer configuration (feeder tables,
 * optional column allow-list). The engine parses migration source files with
 * regex only — it never loads or executes them — so it can safely run on
 * shipped migrations or fixture trees.
 *
 * DevOps wiring against the real migration tree:
 * {@see MigrationIndexerSafeguardTest}.
 *
 * @internal
 */
#[Package('framework')]
final class MigrationIndexerSafeguard
{
    public const LABEL_REAL_MISS = 'REAL_MISS';
    public const LABEL_CROSS_MAJOR = 'CROSS_MAJOR';
    public const LABEL_CROSS_MAJOR_PARTIAL = 'CROSS_MAJOR_PARTIAL';

    public const LABEL_DESCRIPTIONS = [
        self::LABEL_REAL_MISS => 'no later-major migration registers this indexer — shops upgrading past the miss never get a catch-up re-index.',
        self::LABEL_CROSS_MAJOR => 'a later-major migration registers the indexer with a full re-index — shops that traverse that major catch up, but shops pinned at the miss-major stay out-of-sync.',
        self::LABEL_CROSS_MAJOR_PARTIAL => 'a later-major migration registers the indexer but restricts execution to a subset of updaters — cross-major coverage is partial, and miss-major pins remain uncovered.',
    ];

    /**
     * @var array<string, string>|null
     */
    private ?array $migrationCache = null;

    /**
     * @param list<string> $enforcedVersions
     */
    public function __construct(
        private readonly string $migrationBaseDir,
        private readonly array $enforcedVersions,
    ) {
    }

    /**
     * @param list<string> $tables
     * @param array<string, list<string>> $indexedColumnsByTable
     *
     * @return list<string>
     */
    public function findViolations(
        string $indexer,
        array $tables,
        array $indexedColumnsByTable = [],
    ): array {
        // Walk migrations newest-first within each major so a registration
        // found earlier in the pass covers every earlier-timestamp touch in
        // the same major without needing a precomputed latest-by-major map.
        $laterRegistrationInMajor = [];
        $violations = [];

        foreach (array_reverse($this->migrations(), true) as $fqcn => $source) {
            $major = $this->majorFromClass($fqcn);

            if ($this->registersIndexer($source, $indexer)) {
                $laterRegistrationInMajor[$major] = true;
                continue;
            }
            if ($laterRegistrationInMajor[$major] ?? false) {
                continue;
            }
            if (str_contains($source, '@no-indexer-required')) {
                continue;
            }
            if (!$this->migrationTouchesIndexer($source, $tables, $indexedColumnsByTable)) {
                continue;
            }

            $violations[] = $fqcn;
        }

        return array_reverse($violations);
    }

    public function classifyViolation(string $fqcn, string $indexer): string
    {
        $violationOrdinal = $this->majorOrdinal($this->majorFromClass($fqcn));

        $hasFullLater = false;
        $hasPartialLater = false;
        foreach ($this->migrations() as $otherFqcn => $otherSource) {
            if ($this->majorOrdinal($this->majorFromClass($otherFqcn)) <= $violationOrdinal) {
                continue;
            }
            foreach ($this->registrationKinds($otherSource, $indexer) as $kind) {
                if ($kind === 'full') {
                    $hasFullLater = true;
                } else {
                    $hasPartialLater = true;
                }
            }
        }

        if ($hasFullLater) {
            return self::LABEL_CROSS_MAJOR;
        }
        if ($hasPartialLater) {
            return self::LABEL_CROSS_MAJOR_PARTIAL;
        }

        return self::LABEL_REAL_MISS;
    }

    /**
     * @param list<string> $violations
     */
    public function formatFailureMessage(string $indexer, array $violations): string
    {
        if ($violations === []) {
            return '';
        }

        $lines = [\sprintf('Migrations write tables feeding %s without calling registerIndexer(\'%s\'):', $indexer, $indexer)];
        $usedLabels = [];
        foreach ($violations as $class) {
            $label = $this->classifyViolation($class, $indexer);
            $usedLabels[$label] = true;
            $lines[] = \sprintf('  [%s] %s', $label, $class);
        }

        $lines[] = '';
        $lines[] = 'Label meaning:';
        foreach (array_keys($usedLabels) as $label) {
            $lines[] = \sprintf('  %s — %s', $label, self::LABEL_DESCRIPTIONS[$label]);
        }

        $lines[] = '';
        $lines[] = 'Resolve each violation by one of:';
        $lines[] = \sprintf('  (1) add $this->registerIndexer($connection, \'%s\') in update()', $indexer);
        $lines[] = '  (2) add "@no-indexer-required: <reason>" to the migration docblock if the write cannot affect the indexer output';
        $lines[] = '  (3) for a migration that is already shipped, introduce a follow-up migration that carries the registerIndexer() call';
        $lines[] = '';

        return implode(\PHP_EOL, $lines);
    }

    /**
     * @return array<string, string>
     */
    private function migrations(): array
    {
        if ($this->migrationCache === null) {
            $this->migrationCache = iterator_to_array($this->discoverMigrations());
        }

        return $this->migrationCache;
    }

    /**
     * @return \Generator<string, string>
     */
    private function discoverMigrations(): \Generator
    {
        $versionDirs = array_values(array_filter(array_map(
            fn (string $v): string => $this->migrationBaseDir . '/' . $v,
            $this->enforcedVersions
        ), 'is_dir'));
        if ($versionDirs === []) {
            return;
        }

        $finder = (new Finder())
            ->in($versionDirs)
            ->files()
            ->name('Migration*.php')
            ->sortByName();

        foreach ($finder as $file) {
            $source = $file->getContents();
            if (!preg_match('/^namespace\s+([^;]+);/m', $source, $m)) {
                continue;
            }
            yield trim($m[1]) . '\\' . $file->getFilenameWithoutExtension() => $source;
        }
    }

    private function majorFromClass(string $fqcn): string
    {
        return preg_match('/\\\\(V6_\d+)\\\\/', $fqcn, $m) ? $m[1] : '';
    }

    private function majorOrdinal(string $major): int
    {
        return preg_match('/V6_(\d+)/', $major, $m) ? (int) $m[1] : 0;
    }

    /**
     * @param list<string> $tables
     * @param array<string, list<string>> $indexedColumnsByTable
     */
    private function migrationTouchesIndexer(string $source, array $tables, array $indexedColumnsByTable): bool
    {
        foreach ($this->extractDmlOperations($source) as $op) {
            if (!\in_array($op['table'], $tables, true)) {
                continue;
            }

            if ($op['kind'] === 'update') {
                $indexedColumns = $indexedColumnsByTable[$op['table']] ?? null;
                if (
                    $indexedColumns !== null
                    && $op['columns'] !== null
                    && array_intersect($op['columns'], $indexedColumns) === []
                ) {
                    continue;
                }
            }

            return true;
        }

        return false;
    }

    private function registersIndexer(string $source, string $indexer): bool
    {
        return preg_match(
            '/registerIndexer\s*\(\s*\$connection\s*,\s*[\'"]' . preg_quote($indexer, '/') . '[\'"]/',
            $source
        ) === 1;
    }

    /**
     * @return list<'full'|'partial'>
     */
    private function registrationKinds(string $source, string $indexer): array
    {
        $pattern = '/registerIndexer\s*\(\s*\$connection\s*,\s*[\'"]'
            . preg_quote($indexer, '/')
            . '[\'"]\s*(?:,\s*(\[[^\]]*\]))?\s*\)/';
        if (!preg_match_all($pattern, $source, $matches, \PREG_SET_ORDER)) {
            return [];
        }

        $kinds = [];
        foreach ($matches as $match) {
            $literal = isset($match[1]) ? trim($match[1]) : '';
            $inner = $literal === '' ? '' : trim(substr($literal, 1, -1));
            $kinds[] = $inner === '' ? 'full' : 'partial';
        }

        return $kinds;
    }

    /**
     * @return \Generator<int, array{kind: string, table: string, columns: list<string>|null}>
     */
    private function extractDmlOperations(string $source): \Generator
    {
        // Form A: $connection->insert|update|delete('table', [data], ...).
        // The optional array literal is captured only for `update` so we can
        // narrow by column; nested arrays defeat the capture and fall back.
        if (preg_match_all(
            '/\$connection\s*->\s*(insert|update|delete)\s*\(\s*[\'"]([a-z_]+)[\'"](?:\s*,\s*(\[[^\[\]]*\]))?/',
            $source,
            $matches,
            \PREG_SET_ORDER
        )) {
            foreach ($matches as $match) {
                $kind = strtolower($match[1]);
                $columns = null;
                if ($kind === 'update' && isset($match[3])) {
                    $columns = $this->extractArrayKeys($match[3]);
                }
                yield ['kind' => $kind, 'table' => strtolower($match[2]), 'columns' => $columns];
            }
        }

        // Form B: raw SQL INSERT/DELETE/REPLACE — no column introspection needed.
        if (preg_match_all(
            '/\b(INSERT\s+INTO|DELETE\s+FROM|REPLACE\s+INTO)\s+`?([a-z_]+)`?/i',
            $source,
            $matches,
            \PREG_SET_ORDER
        )) {
            foreach ($matches as $match) {
                $kind = stripos($match[1], 'INSERT') === 0
                    ? 'insert'
                    : (stripos($match[1], 'DELETE') === 0 ? 'delete' : 'replace');
                yield ['kind' => $kind, 'table' => strtolower($match[2]), 'columns' => null];
            }
        }

        // Form C: raw SQL UPDATE with SET clause parsing.
        if (preg_match_all(
            '/\bUPDATE\s+`?([a-z_]+)`?\s+SET\s+(.+?)(?:\s+WHERE\b|\s+ORDER\s+BY\b|\s+LIMIT\b|\s*;|\s*$)/is',
            $source,
            $matches,
            \PREG_SET_ORDER
        )) {
            foreach ($matches as $match) {
                yield [
                    'kind' => 'update',
                    'table' => strtolower($match[1]),
                    'columns' => $this->extractSetColumns($match[2]),
                ];
            }
        }
    }

    /**
     * @return list<string>|null
     */
    private function extractArrayKeys(string $arrayLiteral): ?array
    {
        if (!preg_match_all('/[\'"]([a-z_][a-z0-9_]*)[\'"]\s*=>/i', $arrayLiteral, $m)) {
            return null;
        }

        return array_values(array_unique(array_map('strtolower', $m[1])));
    }

    /**
     * @return list<string>|null
     */
    private function extractSetColumns(string $setClause): ?array
    {
        // Column assignments start at offset 0 or after a top-level comma. A
        // simple `col =` regex over the full clause would also match sub-
        // expressions like IF(x = 1, ...); anchor matches to start-of-string
        // or comma. The optional `table.` prefix handles qualified
        // identifiers like `payment_method`.`technical_name`.
        $pattern = '/(?:^|,)\s*(?:`?[a-z_][a-z0-9_]*`?\s*\.\s*)?`?([a-z_][a-z0-9_]*)`?\s*=/i';
        if (!preg_match_all($pattern, $setClause, $m)) {
            return null;
        }

        return array_values(array_unique(array_map('strtolower', $m[1])));
    }
}
