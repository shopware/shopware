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
use Shopware\Core\Migration\V6_7\Migration1795424732FixGoogleProductExportTemplatePrice;
use Shopware\Core\Test\TestDefaults;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(Migration1795424732FixGoogleProductExportTemplatePrice::class)]
class Migration1795424732FixGoogleProductExportTemplatePriceTest extends TestCase
{
    use IntegrationTestBehaviour;

    private const FIXTURES = __DIR__ . '/../../../../src/Core/Migration/Fixtures/productComparison-export-profiles/issue-18322/';

    private const ADMIN_ROOT = __DIR__ . '/../../../../src/Administration/Resources/app/administration/src/module/sw-sales-channel/';

    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1795424732, (new Migration1795424732FixGoogleProductExportTemplatePrice())->getCreationTimestamp());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function templateNameProvider(): iterable
    {
        yield 'google product comparison' => ['google'];
        yield 'agentic commerce google' => ['agentic_google'];
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function adminTemplateProvider(): iterable
    {
        yield 'google product comparison' => ['google', 'product-export-templates/google-product-search-de/body.xml.twig'];
        yield 'agentic commerce google' => ['agentic_google', 'agentic-product-export-templates/google/body.xml.twig'];
    }

    #[DataProvider('templateNameProvider')]
    public function testMigrationUpgradesUnmodifiedTemplate(string $name): void
    {
        $old = $this->readFixture($name . '_old.xml.twig');
        $new = $this->readFixture($name . '_new.xml.twig');
        static::assertStringContainsString('calculatedPrices.last', $old);
        static::assertStringContainsString('calculatedPrices.first', $new);

        $id = $this->insertProductExport($old);

        $migration = new Migration1795424732FixGoogleProductExportTemplatePrice();
        $migration->update($this->connection);
        $migration->update($this->connection);

        $row = $this->fetchProductExport($id);
        static::assertSame($new, $row['body']);
        static::assertNotNull($row['updatedAt']);
    }

    public function testMigrationLeavesCustomerModifiedTemplatesAlone(): void
    {
        $customTemplate = $this->readFixture('google_old.xml.twig') . "<!-- customized -->\n";
        $id = $this->insertProductExport($customTemplate);

        (new Migration1795424732FixGoogleProductExportTemplatePrice())->update($this->connection);

        $row = $this->fetchProductExport($id);
        static::assertSame($customTemplate, $row['body']);
        static::assertNull($row['updatedAt']);
    }

    #[DataProvider('adminTemplateProvider')]
    public function testShippedAdminTemplatesMatchPostFixFixtures(string $name, string $adminPath): void
    {
        $admin = file_get_contents(self::ADMIN_ROOT . $adminPath);
        static::assertNotFalse($admin);
        static::assertSame($admin, $this->readFixture($name . '_new.xml.twig'));
    }

    /**
     * @return array{body: string, updatedAt: ?string}
     */
    private function fetchProductExport(string $id): array
    {
        /** @var array{body: string, updatedAt: ?string}|false $row */
        $row = $this->connection->fetchAssociative(
            'SELECT body_template AS body, updated_at AS updatedAt FROM product_export WHERE id = ?',
            [$id],
        );
        static::assertIsArray($row);

        return $row;
    }

    private function insertProductExport(string $body): string
    {
        $id = Uuid::randomBytes();
        $productStreamId = Uuid::randomBytes();
        $now = (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT);

        $this->connection->insert('product_stream', [
            'id' => $productStreamId,
            'created_at' => $now,
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
                'file_format' => 'xml',
                '`interval`' => 86400,
                'body_template' => $body,
                'created_at' => $now,
                'currency_id' => Uuid::fromHexToBytes(Defaults::CURRENCY),
            ],
            [
                'id' => 'binary',
                'product_stream_id' => 'binary',
                'sales_channel_id' => 'binary',
                'currency_id' => 'binary',
            ],
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
