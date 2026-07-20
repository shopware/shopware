<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Migration;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Migration\MigrationIndexerSafeguard;

/**
 * Fixtures use synthetic majors (`V6_90`, `V6_91`) and a single shared
 * indexer/table pair so each scenario's tree can be read in isolation.
 *
 * For real filesystem-based discovery across the real migration tree:
 * {@see Shopware\Tests\DevOps\Core\Migration\MigrationIndexerSafeguardTest}
 *
 * @internal
 */
#[CoversClass(MigrationIndexerSafeguard::class)]
class MigrationIndexerSafeguardTest extends TestCase
{
    private const INDEXER = 'fixture.indexer';
    private const TABLE = 'fixture_indexed_table';
    private const FIXTURE_NAMESPACE = 'Shopware\\Tests\\Unit\\Core\\Framework\\Migration\\_fixtures\\MigrationIndexerSafeguard\\';

    /**
     * @param list<string> $enforcedVersions
     * @param list<string> $expectedViolations
     * @param array<string, list<string>> $indexedColumnsByTable
     */
    #[DataProvider('provideFindViolationsCases')]
    #[TestDox('findViolations produces the expected violation list: $_dataName')]
    public function testFindViolations(
        string $scenario,
        array $enforcedVersions,
        array $expectedViolations,
        array $indexedColumnsByTable = [],
    ): void {
        $safeguard = $this->safeguard($scenario, $enforcedVersions);

        $violations = $safeguard->findViolations(self::INDEXER, [self::TABLE], $indexedColumnsByTable);

        static::assertSame($expectedViolations, $violations);
    }

    /**
     * @return \Generator<string, array{
     *     scenario: string,
     *     enforcedVersions: list<string>,
     *     expectedViolations: list<string>,
     *     indexedColumnsByTable?: array<string, list<string>>,
     * }>
     */
    public static function provideFindViolationsCases(): \Generator
    {
        yield 'flagged without registration' => [
            'scenario' => 'Flagged',
            'enforcedVersions' => ['V6_90'],
            'expectedViolations' => [self::fixtureMigrationNamespace('Flagged', 'V6_90', 'Migration1000000001Miss')],
        ];
        yield 'migration registers the indexer' => [
            'scenario' => 'Registered',
            'enforcedVersions' => ['V6_90'],
            'expectedViolations' => [],
        ];
        yield 'opt-out annotation suppresses violation' => [
            'scenario' => 'OptedOut',
            'enforcedVersions' => ['V6_90'],
            'expectedViolations' => [],
        ];
        yield 'later same-major registration remediates earlier miss' => [
            'scenario' => 'SameMajorRemediation',
            'enforcedVersions' => ['V6_90'],
            'expectedViolations' => [],
        ];
        yield 'later-major registration does not auto-remediate earlier-major miss' => [
            'scenario' => 'CrossMajor',
            'enforcedVersions' => ['V6_90', 'V6_91'],
            'expectedViolations' => [self::fixtureMigrationNamespace('CrossMajor', 'V6_90', 'Migration1000000001Miss')],
        ];
        yield 'column allow-list skips updates touching only non-indexed columns' => [
            'scenario' => 'UpdateNonIndexedColumn',
            'enforcedVersions' => ['V6_90'],
            'expectedViolations' => [],
            'indexedColumnsByTable' => [self::TABLE => ['watched_column']],
        ];
        yield 'column allow-list flags updates touching indexed columns' => [
            'scenario' => 'UpdateIndexedColumn',
            'enforcedVersions' => ['V6_90'],
            'expectedViolations' => [self::fixtureMigrationNamespace('UpdateIndexedColumn', 'V6_90', 'Migration1000000001UpdateIndexed')],
            'indexedColumnsByTable' => [self::TABLE => ['watched_column']],
        ];
        yield 'raw UPDATE with qualified column is flagged by column allow-list' => [
            'scenario' => 'RawUpdateQualified',
            'enforcedVersions' => ['V6_90'],
            'expectedViolations' => [self::fixtureMigrationNamespace('RawUpdateQualified', 'V6_90', 'Migration1000000001RawUpdate')],
            'indexedColumnsByTable' => [self::TABLE => ['watched_column']],
        ];
        yield 'delete statement triggers violation' => [
            'scenario' => 'DeleteStatement',
            'enforcedVersions' => ['V6_90'],
            'expectedViolations' => [self::fixtureMigrationNamespace('DeleteStatement', 'V6_90', 'Migration1000000001Delete')],
        ];
        yield 'migration touching unrelated table is ignored' => [
            'scenario' => 'UnrelatedTable',
            'enforcedVersions' => ['V6_90'],
            'expectedViolations' => [],
        ];
        yield 'enforced version dir that does not exist is ignored' => [
            'scenario' => 'NonExistent',
            'enforcedVersions' => ['V6_90'],
            'expectedViolations' => [],
        ];
        yield 'migration file without namespace declaration is skipped silently' => [
            'scenario' => 'OrphanFile',
            'enforcedVersions' => ['V6_90'],
            'expectedViolations' => [self::fixtureMigrationNamespace('OrphanFile', 'V6_90', 'Migration1000000002Miss')],
        ];
        yield 'raw SQL INSERT triggers violation' => [
            'scenario' => 'RawSqlInsert',
            'enforcedVersions' => ['V6_90'],
            'expectedViolations' => [self::fixtureMigrationNamespace('RawSqlInsert', 'V6_90', 'Migration1000000001RawInsert')],
        ];
        yield 'raw SQL DELETE triggers violation' => [
            'scenario' => 'RawSqlDelete',
            'enforcedVersions' => ['V6_90'],
            'expectedViolations' => [self::fixtureMigrationNamespace('RawSqlDelete', 'V6_90', 'Migration1000000001RawDelete')],
        ];
        yield 'raw SQL REPLACE triggers violation' => [
            'scenario' => 'RawSqlReplace',
            'enforcedVersions' => ['V6_90'],
            'expectedViolations' => [self::fixtureMigrationNamespace('RawSqlReplace', 'V6_90', 'Migration1000000001RawReplace')],
        ];
        yield 'update with unparseable array literal falls back to flagging the table' => [
            'scenario' => 'UnparseableArrayLiteral',
            'enforcedVersions' => ['V6_90'],
            'expectedViolations' => [self::fixtureMigrationNamespace('UnparseableArrayLiteral', 'V6_90', 'Migration1000000001UnparseableArray')],
            'indexedColumnsByTable' => [self::TABLE => ['watched_column']],
        ];
        yield 'raw UPDATE with unparseable SET clause falls back to flagging the table' => [
            'scenario' => 'UnparseableSetClause',
            'enforcedVersions' => ['V6_90'],
            'expectedViolations' => [self::fixtureMigrationNamespace('UnparseableSetClause', 'V6_90', 'Migration1000000001UnparseableSet')],
            'indexedColumnsByTable' => [self::TABLE => ['watched_column']],
        ];
    }

    /**
     * @param list<string> $enforcedVersions
     */
    #[DataProvider('provideClassifyViolationCases')]
    #[TestDox('classifyViolation returns the expected label: $_dataName')]
    public function testClassifyViolation(
        string $scenario,
        array $enforcedVersions,
        string $violationFqcn,
        string $expectedLabel,
    ): void {
        $safeguard = $this->safeguard($scenario, $enforcedVersions);

        static::assertSame($expectedLabel, $safeguard->classifyViolation($violationFqcn, self::INDEXER));
    }

    /**
     * @return \Generator<string, array{
     *     scenario: string,
     *     enforcedVersions: list<string>,
     *     violationFqcn: string,
     *     expectedLabel: string,
     * }>
     */
    public static function provideClassifyViolationCases(): \Generator
    {
        yield 'real miss when no later-major registers' => [
            'scenario' => 'ClassifyRealMiss',
            'enforcedVersions' => ['V6_90', 'V6_91'],
            'violationFqcn' => self::fixtureMigrationNamespace('ClassifyRealMiss', 'V6_91', 'Migration2000000001Miss'),
            'expectedLabel' => MigrationIndexerSafeguard::LABEL_REAL_MISS,
        ];
        yield 'cross-major when later-major registers fully' => [
            'scenario' => 'ClassifyCrossMajor',
            'enforcedVersions' => ['V6_90', 'V6_91'],
            'violationFqcn' => self::fixtureMigrationNamespace('ClassifyCrossMajor', 'V6_90', 'Migration1000000001Miss'),
            'expectedLabel' => MigrationIndexerSafeguard::LABEL_CROSS_MAJOR,
        ];
        yield 'cross-major partial when only partial registration exists' => [
            'scenario' => 'ClassifyCrossMajorPartial',
            'enforcedVersions' => ['V6_90', 'V6_91'],
            'violationFqcn' => self::fixtureMigrationNamespace('ClassifyCrossMajorPartial', 'V6_90', 'Migration1000000001Miss'),
            'expectedLabel' => MigrationIndexerSafeguard::LABEL_CROSS_MAJOR_PARTIAL,
        ];
        yield 'prefers cross-major when both full and partial later registrations exist' => [
            'scenario' => 'ClassifyFullAndPartial',
            'enforcedVersions' => ['V6_90', 'V6_91'],
            'violationFqcn' => self::fixtureMigrationNamespace('ClassifyFullAndPartial', 'V6_90', 'Migration1000000001Miss'),
            'expectedLabel' => MigrationIndexerSafeguard::LABEL_CROSS_MAJOR,
        ];
    }

    #[TestDox('formatFailureMessage lists each used label once in the legend')]
    public function testFormatFailureMessageListsEveryUsedLabelOnce(): void
    {
        $safeguard = $this->safeguard('FormatLabels', ['V6_90', 'V6_91']);

        $message = $safeguard->formatFailureMessage(self::INDEXER, [
            self::fixtureMigrationNamespace('FormatLabels', 'V6_90', 'Migration1000000001Miss'),
            self::fixtureMigrationNamespace('FormatLabels', 'V6_91', 'Migration2000000001Miss'),
        ]);

        static::assertStringContainsString('[CROSS_MAJOR_PARTIAL]', $message);
        static::assertStringContainsString('[REAL_MISS]', $message);
        static::assertStringContainsString('Label meaning:', $message);
        static::assertStringContainsString(
            'CROSS_MAJOR_PARTIAL — ' . MigrationIndexerSafeguard::LABEL_DESCRIPTIONS[MigrationIndexerSafeguard::LABEL_CROSS_MAJOR_PARTIAL],
            $message,
        );
        static::assertStringContainsString(
            'REAL_MISS — ' . MigrationIndexerSafeguard::LABEL_DESCRIPTIONS[MigrationIndexerSafeguard::LABEL_REAL_MISS],
            $message,
        );
        // CROSS_MAJOR is not in the violation set, so its description must not leak into the legend.
        static::assertStringNotContainsString(MigrationIndexerSafeguard::LABEL_DESCRIPTIONS[MigrationIndexerSafeguard::LABEL_CROSS_MAJOR], $message);
    }

    #[TestDox('formatFailureMessage returns empty string when there are no violations')]
    public function testFormatFailureMessageReturnsEmptyStringForEmptyViolations(): void
    {
        $safeguard = $this->safeguard('Flagged', ['V6_90']);

        static::assertSame('', $safeguard->formatFailureMessage(self::INDEXER, []));
    }

    /**
     * @param list<string> $enforcedVersions
     */
    private function safeguard(string $scenario, array $enforcedVersions): MigrationIndexerSafeguard
    {
        return new MigrationIndexerSafeguard(
            __DIR__ . '/_fixtures/MigrationIndexerSafeguard/' . $scenario,
            $enforcedVersions,
        );
    }

    private static function fixtureMigrationNamespace(string $scenario, string $major, string $className): string
    {
        return self::FIXTURE_NAMESPACE . $scenario . '\\' . $major . '\\' . $className;
    }
}
