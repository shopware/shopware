<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\Migration;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Migration\MigrationIndexerSafeguard;
use Shopware\Core\Test\Assert\StrictEmpty;

/**
 * Flags core migrations that write to tables feeding a DAL indexer without
 * scheduling the corresponding re-index via MigrationStep::registerIndexer().
 *
 * Each indexer has its own data-provider case listing:
 *   - feeder tables
 *   - optional per-table column allow-list (narrows UPDATE detection)
 *
 * Escape hatches:
 * - docblock annotation "@no-indexer-required: <reason>" on the migration
 *   when the write cannot affect the indexer's output (schema-only change,
 *   cleanup, etc.)
 * - for a shipped miss, land a new migration that carries the missing
 *   registerIndexer() call — it auto-remediates same-major misses
 * - refine the data-provider case (tables, column allow-list) when a
 *   violation is a false positive
 *
 * @internal
 */
class MigrationIndexerSafeguardTest extends TestCase
{
    /**
     * Earliest V6_6 major enforced by the safeguard. Migrations in versions
     * below this number predate the indexer-registration conventions this
     * test enforces, so they are skipped. Bump this once an older major is
     * no longer receiving backports.
     */
    private const FIRST_ENFORCED_MAJOR = 6;

    /**
     * @var array<string, MigrationIndexerSafeguard>
     */
    private static array $safeguardByBase = [];

    /**
     * @param list<string> $tables feeder tables for this indexer
     * @param array<string, list<string>> $indexedColumnsByTable optional per-table column
     *                                                           allow-list. An UPDATE on a listed table whose SET columns don't
     *                                                           intersect its list is treated as non-indexer-affecting. UPDATEs
     *                                                           whose columns can't be parsed fall back to flagging the table.
     */
    #[DataProvider('indexerProvider')]
    public function testCoreIndexerIsRegisteredBySameMajorWriters(
        string $indexer,
        array $tables,
        array $indexedColumnsByTable = [],
    ): void {
        self::assertNoViolations('src/Core/Migration', $indexer, $tables, $indexedColumnsByTable);
    }

    /**
     * @param list<string> $tables
     * @param array<string, list<string>> $indexedColumnsByTable
     */
    #[DataProvider('indexerProvider')]
    public function testAdministrationIndexerIsRegisteredBySameMajorWriters(
        string $indexer,
        array $tables,
        array $indexedColumnsByTable = [],
    ): void {
        self::assertNoViolations('src/Administration/Migration', $indexer, $tables, $indexedColumnsByTable);
    }

    /**
     * @param list<string> $tables
     * @param array<string, list<string>> $indexedColumnsByTable
     */
    #[DataProvider('indexerProvider')]
    public function testElasticsearchIndexerIsRegisteredBySameMajorWriters(
        string $indexer,
        array $tables,
        array $indexedColumnsByTable = [],
    ): void {
        self::assertNoViolations('src/Elasticsearch/Migration', $indexer, $tables, $indexedColumnsByTable);
    }

    /**
     * @param list<string> $tables
     * @param array<string, list<string>> $indexedColumnsByTable
     */
    #[DataProvider('indexerProvider')]
    public function testStorefrontIndexerIsRegisteredBySameMajorWriters(
        string $indexer,
        array $tables,
        array $indexedColumnsByTable = [],
    ): void {
        self::assertNoViolations('src/Storefront/Migration', $indexer, $tables, $indexedColumnsByTable);
    }

    /**
     * Uses associative arrays so PHPUnit unpacks entries by name — each case
     * only specifies the parameters it needs, and readers see the meaning of
     * each value at the call site.
     *
     * @return \Generator<string, array{
     *     indexer: string,
     *     tables: list<string>,
     *     indexedColumnsByTable?: array<string, list<string>>,
     * }>
     */
    public static function indexerProvider(): \Generator
    {
        yield 'category.indexer' => [
            'indexer' => 'category.indexer',
            'tables' => ['category'],
            // CategoryIndexer updaters only react to parent_id changes (tree
            // + child-count) and category_translation.name (breadcrumb).
            // Other category columns are invisible to the indexer.
            'indexedColumnsByTable' => ['category' => ['parent_id']],
        ];
        yield 'customer.indexer' => [
            'indexer' => 'customer.indexer',
            'tables' => ['customer'],
            // CustomerIndexer::PRIMARY_KEYS_WITH_PROPERTY_CHANGE +
            // CustomerNewsletterSalesChannelsUpdater inputs.
            'indexedColumnsByTable' => ['customer' => ['email', 'first_name', 'last_name']],
        ];
        yield 'flow.indexer' => [
            'indexer' => 'flow.indexer',
            'tables' => ['flow', 'flow_sequence', 'flow_template'],
        ];
        yield 'landing_page.indexer' => [
            'indexer' => 'landing_page.indexer',
            'tables' => ['landing_page'],
        ];
        yield 'media.indexer' => [
            'indexer' => 'media.indexer',
            'tables' => ['media', 'media_thumbnail'],
        ];
        yield 'media_folder.indexer' => [
            'indexer' => 'media_folder.indexer',
            'tables' => ['media_folder'],
        ];
        yield 'media_folder_configuration.indexer' => [
            'indexer' => 'media_folder_configuration.indexer',
            'tables' => ['media_folder_configuration'],
        ];
        yield 'newsletter_recipient.indexer' => [
            'indexer' => 'newsletter_recipient.indexer',
            'tables' => ['newsletter_recipient'],
        ];
        yield 'payment_method.indexer' => [
            'indexer' => 'payment_method.indexer',
            'tables' => ['payment_method'],
            // PaymentDistinguishableNameGenerator reads: plugin via plugin_id
            // FK, appPaymentMethod (via reverse FK on payment_method.id, not a
            // payment_method column), payment_method_translation.name. Other
            // payment_method columns (technical_name, handler_identifier,
            // active, distinguishable_name) are not inputs.
            'indexedColumnsByTable' => ['payment_method' => ['plugin_id']],
        ];
        yield 'product.indexer' => [
            'indexer' => 'product.indexer',
            'tables' => ['product', 'product_category', 'product_option', 'product_property'],
        ];
        yield 'product_stream.indexer' => [
            'indexer' => 'product_stream.indexer',
            'tables' => ['product_stream', 'product_stream_filter'],
        ];
        yield 'promotion.indexer' => [
            'indexer' => 'promotion.indexer',
            'tables' => ['promotion'],
        ];
        yield 'rule.indexer' => [
            'indexer' => 'rule.indexer',
            'tables' => ['rule', 'rule_condition'],
        ];
        yield 'sales_channel.indexer' => [
            'indexer' => 'sales_channel.indexer',
            'tables' => ['sales_channel'],
        ];
        yield 'theme.indexer' => [
            'indexer' => 'theme.indexer',
            'tables' => ['theme'],
        ];
    }

    /**
     * @param list<string> $tables
     * @param array<string, list<string>> $indexedColumnsByTable
     */
    private static function assertNoViolations(
        string $relativeBase,
        string $indexer,
        array $tables,
        array $indexedColumnsByTable,
    ): void {
        $safeguard = self::cachedSafeguard($relativeBase);

        $violations = $safeguard->findViolations($indexer, $tables, $indexedColumnsByTable);

        StrictEmpty::assertEmpty($violations, $safeguard->formatFailureMessage($indexer, $violations));
    }

    /**
     * Shared across data-provider cases; migrations don't change between
     * test methods within a run.
     */
    private static function cachedSafeguard(string $relativeBase): MigrationIndexerSafeguard
    {
        if (!isset(self::$safeguardByBase[$relativeBase])) {
            self::$safeguardByBase[$relativeBase] = new MigrationIndexerSafeguard(
                __DIR__ . '/../../../../' . $relativeBase,
                self::enforcedVersions(),
            );
        }

        return self::$safeguardByBase[$relativeBase];
    }

    /**
     * Migration version directories in scope for this safeguard, derived
     * from registered `vX.Y.Z.W` feature flags. Constrained to
     * V6_{FIRST_ENFORCED_MAJOR}+ so older majors whose migrations predate
     * the indexer conventions enforced here are skipped.
     *
     * @return list<string>
     */
    private static function enforcedVersions(): array
    {
        $majors = [];
        foreach (array_keys(Feature::getAll(false)) as $name) {
            if (!preg_match('/^V6_(\d+)_\d+_\d+$/', $name, $m)) {
                continue;
            }
            $major = (int) $m[1];
            if ($major < self::FIRST_ENFORCED_MAJOR) {
                continue;
            }
            $majors['V6_' . $major] = true;
        }
        $versions = array_keys($majors);
        sort($versions);

        return $versions;
    }
}
