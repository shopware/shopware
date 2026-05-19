<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Migration\V6_7\Migration1779177686UpdateProductComparisonGoogleTemplateForFeedLabel;
use Shopware\Core\Test\TestDefaults;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @internal
 */
#[CoversClass(Migration1779177686UpdateProductComparisonGoogleTemplateForFeedLabel::class)]
class Migration1779177686UpdateProductComparisonGoogleTemplateForFeedLabelTest extends TestCase
{
    use IntegrationTestBehaviour;

    private Connection $connection;

    private string $oldTemplate;

    private string $newTemplate;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
        $fs = new Filesystem();
        $fixtureBase = __DIR__ . '/../../../../src/Core/Migration/Fixtures/productComparison-export-profiles/gh-9421/';
        $this->oldTemplate = $fs->readFile($fixtureBase . 'google_old.xml.twig');
        $this->newTemplate = $fs->readFile($fixtureBase . 'google_new.xml.twig');
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(
            1779177686,
            (new Migration1779177686UpdateProductComparisonGoogleTemplateForFeedLabel())->getCreationTimestamp()
        );
    }

    public function testMigrationReplacesOnlyUnchangedRows(): void
    {
        $unchangedId = $this->insertProductExport($this->oldTemplate);
        $customizedTemplate = $this->oldTemplate . "\n<!-- merchant override -->\n";
        $customizedId = $this->insertProductExport($customizedTemplate);

        (new Migration1779177686UpdateProductComparisonGoogleTemplateForFeedLabel())->update($this->connection);

        $unchangedAfter = $this->connection->fetchOne(
            'SELECT body_template FROM product_export WHERE id = :id',
            ['id' => $unchangedId]
        );
        $customizedAfter = $this->connection->fetchOne(
            'SELECT body_template FROM product_export WHERE id = :id',
            ['id' => $customizedId]
        );

        static::assertSame($this->newTemplate, $unchangedAfter);
        static::assertSame($customizedTemplate, $customizedAfter);
    }

    /**
     * Inserts a minimal product_export row, creating the required product_stream parent on the fly.
     * IntegrationTestBehaviour wraps each test in a transaction that is rolled back on teardown.
     */
    private function insertProductExport(string $bodyTemplate): string
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
                'currency_id' => Uuid::fromHexToBytes(Defaults::CURRENCY),
                'file_name' => Uuid::randomHex(),
                'access_key' => 'access-key-' . bin2hex($id),
                'encoding' => 'UTF-8',
                'file_format' => 'xml',
                '`interval`' => 300,
                'body_template' => $bodyTemplate,
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
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
}
