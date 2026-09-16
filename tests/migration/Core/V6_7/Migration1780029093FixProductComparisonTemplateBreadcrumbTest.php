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
use Shopware\Core\Migration\V6_7\Migration1780029093FixProductComparisonTemplateBreadcrumb;
use Shopware\Core\Test\TestDefaults;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(Migration1780029093FixProductComparisonTemplateBreadcrumb::class)]
class Migration1780029093FixProductComparisonTemplateBreadcrumbTest extends TestCase
{
    use IntegrationTestBehaviour;

    private const FIXTURES = __DIR__ . '/../../../../src/Core/Migration/Fixtures/productComparison-export-profiles/issue-12852/';

    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testGetCreationTimestamp(): void
    {
        $migration = new Migration1780029093FixProductComparisonTemplateBreadcrumb();
        static::assertSame(1780029093, $migration->getCreationTimestamp());
    }

    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function vendorProvider(): iterable
    {
        // The bool mirrors the per-vendor `body.trim()` behaviour in
        // src/Administration/.../product-export-templates/{vendor}/index.js.
        yield 'google' => ['google.xml', false];
        yield 'idealo' => ['idealo.csv', true];
        yield 'billiger' => ['billiger.csv', true];
    }

    #[DataProvider('vendorProvider')]
    public function testMigrationUpgradesUnmodifiedTemplate(string $name, bool $adminTrims): void
    {
        [$base, $ext] = explode('.', $name);
        $pre = $this->readFixture($base . '_old.' . $ext . '.twig');
        $post = $this->readFixture($base . '_new.' . $ext . '.twig');
        if ($adminTrims) {
            $pre = trim($pre);
            $post = trim($post);
        }
        static::assertNotSame($pre, $post, 'fixture sanity: pre and post must differ for ' . $name);

        $id = $this->prepareOldDatabaseEntry($pre);

        (new Migration1780029093FixProductComparisonTemplateBreadcrumb())->update($this->connection);

        $row = $this->getCurrentBodyAndUpdateTimestamp($id);
        static::assertNotFalse($row);
        static::assertSame($post, $row['body']);
        static::assertNotNull($row['updatedAt']);
    }

    public function testMigrationLeavesCustomerModifiedTemplatesAlone(): void
    {
        $customTemplate = "<item>{{ product.id }} -- customized</item>\n";
        $id = $this->prepareOldDatabaseEntry($customTemplate);

        (new Migration1780029093FixProductComparisonTemplateBreadcrumb())->update($this->connection);

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
            $fixture = $this->readFixture($base . '_new.' . $ext . '.twig');
            $admin = file_get_contents($adminRoot . $adminPath);
            static::assertNotFalse($admin);
            static::assertSame($admin, $fixture, "issue-12852 post-fix fixture for $name has drifted from the admin starter template at $adminPath");
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
