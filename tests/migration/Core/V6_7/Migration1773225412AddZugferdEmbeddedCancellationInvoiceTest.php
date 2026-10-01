<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Document\Renderer\ZugferdEmbeddedCancellationInvoiceRenderer;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Migration\V6_7\Migration1773225412AddZugferdEmbeddedCancellationInvoice;

/**
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(Migration1773225412AddZugferdEmbeddedCancellationInvoice::class)]
class Migration1773225412AddZugferdEmbeddedCancellationInvoiceTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1773225412, (new Migration1773225412AddZugferdEmbeddedCancellationInvoice())->getCreationTimestamp());
    }

    public function testAddZugferdCancellationInvoice(): void
    {
        $existing = $this->connection->fetchOne(
            'SELECT id FROM document_type WHERE technical_name = :technicalName',
            ['technicalName' => ZugferdEmbeddedCancellationInvoiceRenderer::TYPE]
        );

        if ($existing) {
            $this->connection->executeStatement(
                'DELETE FROM `document_type_translation` WHERE document_type_id = :documentTypeId',
                ['documentTypeId' => $existing]
            );

            $this->connection->executeStatement(
                'DELETE FROM `document_type` WHERE technical_name = :technicalName',
                ['technicalName' => ZugferdEmbeddedCancellationInvoiceRenderer::TYPE]
            );
        }

        $migration = new Migration1773225412AddZugferdEmbeddedCancellationInvoice();
        $migration->update($this->connection);
        $migration->update($this->connection);

        $documentType = $this->connection->fetchAssociative(
            'SELECT LOWER(HEX(id)) as id, technical_name
                 FROM document_type
                 WHERE technical_name = :technicalName',
            ['technicalName' => ZugferdEmbeddedCancellationInvoiceRenderer::TYPE]
        );

        static::assertIsArray($documentType);
        static::assertArrayHasKey('technical_name', $documentType);
        static::assertSame(ZugferdEmbeddedCancellationInvoiceRenderer::TYPE, $documentType['technical_name']);

        $documentTypeTranslations = $this->connection->fetchAllAssociative(
            'SELECT name, language_id
                 FROM document_type_translation
                 WHERE document_type_id = :documentTypeId
                 ORDER BY created_at',
            ['documentTypeId' => Uuid::fromHexToBytes($documentType['id'])]
        );

        $translations = array_column($documentTypeTranslations, 'name');
        sort($translations);

        static::assertSame(
            [
                'ZUGFeRD Cancellation Invoice (embedded)',
                'ZUGFeRD Stornorechnung (eingebettet)',
            ],
            $translations,
        );
    }
}
