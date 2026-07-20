<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Document\Renderer\ZugferdCancellationInvoiceRenderer;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Migration\V6_7\Migration1773047964AddZugferdCancellationInvoice;

/**
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(Migration1773047964AddZugferdCancellationInvoice::class)]
class Migration1773047964AddZugferdCancellationInvoiceTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1773047964, (new Migration1773047964AddZugferdCancellationInvoice())->getCreationTimestamp());
    }

    public function testAddZugferdCancellationInvoice(): void
    {
        $existing = $this->connection->fetchOne(
            'SELECT id FROM document_type WHERE technical_name = :technicalName',
            ['technicalName' => ZugferdCancellationInvoiceRenderer::TYPE]
        );

        if ($existing) {
            $this->connection->executeStatement(
                'DELETE FROM `document_type_translation` WHERE document_type_id = :documentTypeId',
                ['documentTypeId' => $existing]
            );

            $this->connection->executeStatement(
                'DELETE FROM `document_type` WHERE technical_name = :technicalName',
                ['technicalName' => ZugferdCancellationInvoiceRenderer::TYPE]
            );
        }

        $migration = new Migration1773047964AddZugferdCancellationInvoice();
        $migration->update($this->connection);
        $migration->update($this->connection);

        $documentType = $this->connection->fetchAssociative(
            'SELECT LOWER(HEX(id)) as id, technical_name
                 FROM document_type
                 WHERE technical_name = :technicalName',
            ['technicalName' => ZugferdCancellationInvoiceRenderer::TYPE]
        );

        static::assertIsArray($documentType);
        static::assertArrayHasKey('technical_name', $documentType);
        static::assertSame(ZugferdCancellationInvoiceRenderer::TYPE, $documentType['technical_name']);

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
                'ZUGFeRD Cancellation Invoice',
                'ZUGFeRD Stornorechnung',
            ],
            $translations,
        );
    }
}
