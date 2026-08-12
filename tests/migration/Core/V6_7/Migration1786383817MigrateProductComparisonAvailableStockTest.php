<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Migration\V6_7\Migration1786383817MigrateProductComparisonAvailableStock;
use Shopware\Core\Test\TestDefaults;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(Migration1786383817MigrateProductComparisonAvailableStock::class)]
class Migration1786383817MigrateProductComparisonAvailableStockTest extends TestCase
{
    use IntegrationTestBehaviour;

    private const FIXTURES = __DIR__ . '/../../../../src/Core/Migration/Fixtures/productComparison-export-profiles/issue-7787/';

    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testGetCreationTimestamp(): void
    {
        $migration = new Migration1786383817MigrateProductComparisonAvailableStock();
        static::assertSame(1786383817, $migration->getCreationTimestamp());
    }

    /**
     * @return iterable<string, array{string, string, bool}>
     */
    public static function vendorProvider(): iterable
    {
        // The bool mirrors the per-vendor `body.trim()` behaviour in
        // src/Administration/.../product-export-templates/{vendor}/index.js.
        foreach ([
            'current',
            'legacy-seo-url',
        ] as $variant) {
            yield $variant . ' google' => [$variant, 'google.xml', false];
            yield $variant . ' idealo' => [$variant, 'idealo.csv', true];
            yield $variant . ' billiger' => [$variant, 'billiger.csv', true];
        }
    }

    #[DataProvider('vendorProvider')]
    public function testMigrationUpgradesUnmodifiedTemplate(string $variant, string $name, bool $adminTrims): void
    {
        [$base, $ext] = explode('.', $name);
        $pre = $this->readFixture($variant . '/' . $base . '_old.' . $ext . '.twig');
        $post = $this->readFixture($variant . '/' . $base . '_new.' . $ext . '.twig');
        if ($adminTrims) {
            $pre = trim($pre);
            $post = trim($post);
        }
        static::assertStringContainsString('product.availableStock', $pre);
        static::assertStringNotContainsString('product.availableStock', $post);

        $id = $this->prepareOldDatabaseEntry($pre);

        (new Migration1786383817MigrateProductComparisonAvailableStock())->update($this->connection);

        $row = $this->getCurrentBodyAndUpdateTimestamp($id);
        static::assertNotFalse($row);
        static::assertSame($post, $row['body']);
        static::assertNotNull($row['updatedAt']);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function variantVendorProvider(): iterable
    {
        foreach (self::vendorProvider() as $key => [$variant, $name]) {
            yield $key => [$variant, $name];
        }
    }

    #[DataProvider('variantVendorProvider')]
    public function testMigrationOnlyRenamesTheStockAccessor(string $variant, string $name): void
    {
        // The legacy variant must keep `seoUrl(...)`: this migration renames the stock
        // accessor, it does not retroactively apply the `entitySeoUrl(...)` change from
        // shopware/shopware#17991, which shipped without a migration of its own.
        [$base, $ext] = explode('.', $name);
        $pre = $this->readFixture($variant . '/' . $base . '_old.' . $ext . '.twig');
        $post = $this->readFixture($variant . '/' . $base . '_new.' . $ext . '.twig');

        static::assertSame(
            str_replace('product.availableStock', 'product.stock', $pre),
            $post,
            "issue-7787/$variant fixtures for $name differ in more than the stock accessor"
        );
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function legacyPredecessorProvider(): iterable
    {
        yield 'google' => ['google.xml'];
        yield 'idealo' => ['idealo.csv'];
        yield 'billiger' => ['billiger.csv'];
    }

    #[DataProvider('legacyPredecessorProvider')]
    public function testLegacyVariantIsTheOnlyDifferenceFromTheCurrentOne(string $name): void
    {
        // Guard for the regression this variant exists to fix: the two predecessors must
        // describe the same template except for the SEO URL helper. If a future change to
        // the starter templates lands without extending this list, the `current` snapshot
        // stops matching the installed base and the migration silently becomes a no-op.
        [$base, $ext] = explode('.', $name);
        $legacy = $this->readFixture('legacy-seo-url/' . $base . '_old.' . $ext . '.twig');
        $current = $this->readFixture('current/' . $base . '_old.' . $ext . '.twig');

        static::assertNotSame($legacy, $current);
        static::assertStringContainsString('seoUrl(\'frontend.detail.page\'', $legacy);
        static::assertStringContainsString('entitySeoUrl(\'product\'', $current);
        static::assertSame(
            preg_replace("/entitySeoUrl\('product', product\.id\)/", 'seoUrl(\'frontend.detail.page\', {\'productId\': product.id})', $current),
            $legacy,
            "issue-7787 legacy-seo-url snapshot for $name differs from the current one in more than the SEO URL helper"
        );
    }

    public function testMigrationLeavesCustomerModifiedTemplatesAlone(): void
    {
        $customTemplate = "<item>{{ product.availableStock }} -- customized</item>\n";
        $id = $this->prepareOldDatabaseEntry($customTemplate);

        (new Migration1786383817MigrateProductComparisonAvailableStock())->update($this->connection);

        $row = $this->getCurrentBodyAndUpdateTimestamp($id);
        static::assertNotFalse($row);
        static::assertSame($customTemplate, $row['body']);
    }

    public function testShippedAdminTemplatesMatchPostFixFixtures(): void
    {
        // Guard: ensure the snapshotted post-fix fixtures stay in sync with the
        // canonical admin starter templates. Drift between them silently breaks
        // future migrations.
        $adminRoot = __DIR__ . '/../../../../src/Administration/Resources/app/administration/src/module/sw-sales-channel/product-export-templates/';

        foreach ([
            'google.xml' => 'google-product-search-de/body.xml.twig',
            'idealo.csv' => 'idealo/body.csv.twig',
            'billiger.csv' => 'billiger-de/body.csv.twig',
        ] as $name => $adminPath) {
            [$base, $ext] = explode('.', $name);
            $fixture = $this->readFixture('current/' . $base . '_new.' . $ext . '.twig');
            $admin = file_get_contents($adminRoot . $adminPath);
            static::assertNotFalse($admin);
            static::assertSame($admin, $fixture, "issue-7787 post-fix fixture for $name has drifted from the admin starter template at $adminPath");
        }
    }

    /**
     * @return array{body: string, updatedAt: ?string}|false
     */
    private function getCurrentBodyAndUpdateTimestamp(string $id): array|false
    {
        $sql = <<<'SQL'
            SELECT body_template AS body, updated_at AS updatedAt
            FROM product_export
            WHERE id = ?
        SQL;

        /** @var array{body: string, updatedAt: ?string}|false $row */
        $row = $this->connection->fetchAssociative($sql, [$id]);

        return $row;
    }

    private function prepareOldDatabaseEntry(string $body): string
    {
        $id = Uuid::randomBytes();
        $productStreamId = Uuid::randomBytes();

        $this->connection->insert('product_stream', [
            'id' => $productStreamId,
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);

        $this->connection->insert(
            'product_export',
            [
                'id' => $id,
                'product_stream_id' => $productStreamId,
                'sales_channel_id' => Uuid::fromHexToBytes(TestDefaults::SALES_CHANNEL),
                'file_name' => Uuid::randomHex(),
                'access_key' => 'SWPERXF3DUTXS0JGRWRWWDHMTA',
                'encoding' => 'UTF-8',
                'file_format' => 'test',
                '`interval`' => 300,
                'body_template' => $body,
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                'currency_id' => Uuid::fromHexToBytes(Defaults::CURRENCY),
            ],
            [
                'id' => 'binary',
                'product_stream_id' => 'binary',
                'sales_channel_id' => 'binary',
                'currency_id' => 'binary',
            ]
        );

        return $id;
    }

    private function readFixture(string $name): string
    {
        $content = file_get_contents(self::FIXTURES . $name);
        static::assertNotFalse($content);

        return $content;
    }
}
