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

    private const ADMIN_TEMPLATES = __DIR__ . '/../../../../src/Administration/Resources/app/administration/src/module/sw-sales-channel/product-export-templates/';

    /**
     * The body Migration1780029093FixProductComparisonTemplateBreadcrumb wrote. Its `_new`
     * snapshots are the bodies shops actually have stored, so they are the closest thing to
     * a real pre-upgrade template this suite can assert against.
     */
    private const SHIPPED_SNAPSHOTS = __DIR__ . '/../../../../src/Core/Migration/Fixtures/productComparison-export-profiles/issue-12852/';

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
     * @return iterable<string, array{string}>
     */
    public static function shippedBodyProvider(): iterable
    {
        yield 'google' => ['google_new.xml.twig'];
        yield 'idealo' => ['idealo_new.csv.twig'];
        yield 'billiger' => ['billiger_new.csv.twig'];
    }

    #[DataProvider('shippedBodyProvider')]
    public function testMigrationRenamesTheAccessorInAShippedBody(string $fixture): void
    {
        $pre = $this->readShippedSnapshot($fixture);
        static::assertStringContainsString('product.availableStock', $pre, 'fixture sanity: the snapshot must still use the deprecated accessor');

        $id = $this->prepareDatabaseEntry(body: $pre);

        $this->migrate();

        $row = $this->getRow($id);
        static::assertSame(str_replace('product.availableStock', 'product.stock', $pre), $row['body']);
        static::assertNotNull($row['updatedAt']);
    }

    /**
     * The point of the accessor-level rename: a template a merchant edited keeps its edits
     * and still loses the deprecated accessor. Snapshot matching skipped these rows entirely.
     */
    public function testMigrationRenamesTheAccessorInAnEditedTemplate(): void
    {
        $edited = <<<'TWIG'
            {# our own feed, hand written #}
            <item>
                <sku>{{ product.productNumber }}</sku>
                <qty>{{ product.availableStock }}</qty>
                {% if product.availableStock > 0 %}<available>1</available>{% endif %}
            </item>
            TWIG;

        $id = $this->prepareDatabaseEntry(body: $edited);

        $this->migrate();

        $row = $this->getRow($id);
        static::assertStringNotContainsString('product.availableStock', $row['body']);
        static::assertStringContainsString('<qty>{{ product.stock }}</qty>', $row['body']);
        static::assertStringContainsString('{% if product.stock > 0 %}', $row['body']);
        static::assertStringContainsString('{# our own feed, hand written #}', $row['body'], 'the rest of the template must be preserved');
    }

    public function testMigrationRenamesTheAccessorInHeaderAndFooter(): void
    {
        $id = $this->prepareDatabaseEntry(
            body: '<item>{{ product.productNumber }}</item>',
            header: '{# total {{ product.availableStock }} #}',
            footer: '<total>{{ product.availableStock }}</total>',
        );

        $this->migrate();

        $row = $this->getRow($id);
        static::assertSame('{# total {{ product.stock }} #}', $row['header']);
        static::assertSame('<total>{{ product.stock }}</total>', $row['footer']);
        static::assertSame('<item>{{ product.productNumber }}</item>', $row['body']);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function unrelatedAccessorProvider(): iterable
    {
        // Deliberately lower case, so the string genuinely contains `product.availableStock`
        // and the match has to be rejected by the word boundary rather than by case.
        yield 'different root variable' => ['<qty>{{ myproduct.availableStock }}</qty>'];
        yield 'longer property name' => ['<qty>{{ product.availableStockLevel }}</qty>'];
        yield 'aliased product variable' => ['{% set p = product %}<qty>{{ p.availableStock }}</qty>'];
    }

    #[DataProvider('unrelatedAccessorProvider')]
    public function testMigrationLeavesAccessorsOnOtherVariablesAlone(string $template): void
    {
        $id = $this->prepareDatabaseEntry(body: $template);

        $this->migrate();

        $row = $this->getRow($id);
        static::assertSame($template, $row['body']);
    }

    public function testMigrationLeavesTemplatesWithoutTheAccessorUntouched(): void
    {
        $id = $this->prepareDatabaseEntry(body: '<item>{{ product.stock }}</item>');

        $this->migrate();

        $row = $this->getRow($id);
        static::assertSame('<item>{{ product.stock }}</item>', $row['body']);
        static::assertNull($row['updatedAt'], 'a row with nothing to rename must not be touched');
    }

    public function testMigrationIsIdempotent(): void
    {
        $id = $this->prepareDatabaseEntry(body: '<qty>{{ product.availableStock }}</qty>');

        $this->migrate();
        $afterFirstRun = $this->getRow($id);

        $this->migrate();

        static::assertSame($afterFirstRun['body'], $this->getRow($id)['body']);
    }

    public function testShippedAdminTemplatesUseTheReplacement(): void
    {
        // Guard: the starter templates a merchant copies must not reintroduce the accessor
        // the migration just removed from every stored template.
        foreach ([
            'google-product-search-de/body.xml.twig',
            'idealo/body.csv.twig',
            'billiger-de/body.csv.twig',
        ] as $adminPath) {
            $admin = file_get_contents(self::ADMIN_TEMPLATES . $adminPath);
            static::assertNotFalse($admin);
            static::assertStringNotContainsString('product.availableStock', $admin, "the starter template at $adminPath still uses the deprecated accessor");
        }
    }

    /**
     * @return array{header: ?string, body: string, footer: ?string, updatedAt: ?string}
     */
    private function getRow(string $id): array
    {
        $sql = <<<'SQL'
            SELECT header_template AS header, body_template AS body, footer_template AS footer, updated_at AS updatedAt
            FROM product_export
            WHERE id = ?
        SQL;

        /** @var array{header: ?string, body: string, footer: ?string, updatedAt: ?string}|false $row */
        $row = $this->connection->fetchAssociative($sql, [$id]);
        static::assertNotFalse($row);

        return $row;
    }

    private function migrate(): void
    {
        (new Migration1786383817MigrateProductComparisonAvailableStock())->update($this->connection);
    }

    private function prepareDatabaseEntry(string $body, ?string $header = null, ?string $footer = null): string
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
                'header_template' => $header,
                'body_template' => $body,
                'footer_template' => $footer,
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

    private function readShippedSnapshot(string $name): string
    {
        $content = file_get_contents(self::SHIPPED_SNAPSHOTS . $name);
        static::assertNotFalse($content);

        return $content;
    }
}
