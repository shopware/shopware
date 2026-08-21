<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_9;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\DocumentV2\DocumentFormat;
use Shopware\Core\Checkout\DocumentV2\DocumentType;
use Shopware\Core\Checkout\Order\OrderStates;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Migration\V6_9\Migration1787292621BackfillDocumentFiles;
use Shopware\Core\Test\TestDefaults;
use Shopware\Tests\Migration\MigrationTestTrait;

/**
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(Migration1787292621BackfillDocumentFiles::class)]
class Migration1787292621BackfillDocumentFilesTest extends TestCase
{
    use MigrationTestTrait;

    private const CREATED_AT = '2024-01-01 00:00:00.000';

    private Connection $connection;

    private string $orderId;

    private string $documentTypeId;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
        $this->documentTypeId = $this->fetchDocumentTypeId();
        $this->orderId = $this->createOrder();
    }

    public function testBackfillsPrimaryPdfAndAccessibilityHtml(): void
    {
        $primaryMedia = $this->createMedia('pdf');
        $accessibilityMedia = $this->createMedia('html');

        $documentId = $this->createDocument(
            DocumentType::INVOICE->value,
            $primaryMedia,
            $accessibilityMedia
        );

        (new Migration1787292621BackfillDocumentFiles())->update($this->connection);

        static::assertSame(
            [
                DocumentFormat::HTML->value => $this->hex($accessibilityMedia),
                DocumentFormat::PDF->value => $this->hex($primaryMedia),
            ],
            $this->fetchFilesByFormat($documentId)
        );

        static::assertSame(
            DocumentType::INVOICE->value,
            $this->fetchTypeName($documentId)
        );
    }

    #[DataProvider('primaryFormatByExtensionProvider')]
    public function testPrimaryFormatIsClassifiedByExtension(?string $fileExtension, string $expectedFormat): void
    {
        $primaryMedia = $this->createMedia($fileExtension);
        $documentId = $this->createDocument(DocumentType::INVOICE->value, $primaryMedia, null);

        (new Migration1787292621BackfillDocumentFiles())->update($this->connection);

        static::assertSame([$expectedFormat => $this->hex($primaryMedia)], $this->fetchFilesByFormat($documentId));
    }

    public static function primaryFormatByExtensionProvider(): \Generator
    {
        yield 'pdf extension => pdf format' => ['pdf', DocumentFormat::PDF->value];
        yield 'html extension => html format' => ['html', DocumentFormat::HTML->value];
        yield 'xml extension => zugferd xml format' => ['xml', DocumentFormat::ZUGFERD_XML->value];
        yield 'extension is matched case-insensitively' => ['PDF', DocumentFormat::PDF->value];
        yield 'unknown extension is preserved verbatim' => ['xlsx', 'xlsx'];
    }

    #[DataProvider('zugferdReassignmentProvider')]
    public function testZugferdTypesAreReassignedAndKeepTheirFormat(string $legacyType, string $expectedBaseType, string $expectedFormat): void
    {
        $primaryMedia = $this->createMedia('bin');
        $documentId = $this->createDocument($legacyType, $primaryMedia, null);

        (new Migration1787292621BackfillDocumentFiles())->update($this->connection);

        static::assertSame([$expectedFormat => $this->hex($primaryMedia)], $this->fetchFilesByFormat($documentId));
        static::assertSame($expectedBaseType, $this->fetchTypeName($documentId));
    }

    public static function zugferdReassignmentProvider(): \Generator
    {
        yield 'zugferd invoice becomes an invoice with a zugferd xml file' => [
            'zugferd_invoice', DocumentType::INVOICE->value, DocumentFormat::ZUGFERD_XML->value,
        ];
        yield 'embedded zugferd invoice becomes an invoice with an embedded pdf file' => [
            'zugferd_embedded_invoice', DocumentType::INVOICE->value, DocumentFormat::ZUGFERD_EMBEDDED_PDF->value,
        ];
        yield 'zugferd cancellation invoice becomes a storno with a zugferd xml file' => [
            'zugferd_cancellation_invoice', DocumentType::CANCELLATION_INVOICE->value, DocumentFormat::ZUGFERD_XML->value,
        ];
        yield 'embedded zugferd cancellation invoice becomes a storno with an embedded pdf file' => [
            'zugferd_embedded_cancellation_invoice', DocumentType::CANCELLATION_INVOICE->value, DocumentFormat::ZUGFERD_EMBEDDED_PDF->value,
        ];
        yield 'zugferd credit note becomes a credit note with a zugferd xml file' => [
            'zugferd_credit_note', DocumentType::CREDIT_NOTE->value, DocumentFormat::ZUGFERD_XML->value,
        ];
        yield 'embedded zugferd credit note becomes a credit note with an embedded pdf file' => [
            'zugferd_embedded_credit_note', DocumentType::CREDIT_NOTE->value, DocumentFormat::ZUGFERD_EMBEDDED_PDF->value,
        ];
    }

    public function testDocumentWithOnlyAccessibilityMediaGetsAnHtmlRow(): void
    {
        $accessibilityMedia = $this->createMedia('html');
        $documentId = $this->createDocument(DocumentType::INVOICE->value, null, $accessibilityMedia);

        (new Migration1787292621BackfillDocumentFiles())->update($this->connection);

        static::assertSame([DocumentFormat::HTML->value => $this->hex($accessibilityMedia)], $this->fetchFilesByFormat($documentId));
    }

    public function testUnclassifiablePrimaryIsSkippedButAccessibilityIsKept(): void
    {
        $primaryMedia = $this->createMedia(null);
        $accessibilityMedia = $this->createMedia('html');
        $documentId = $this->createDocument(DocumentType::INVOICE->value, $primaryMedia, $accessibilityMedia);

        (new Migration1787292621BackfillDocumentFiles())->update($this->connection);

        static::assertSame([DocumentFormat::HTML->value => $this->hex($accessibilityMedia)], $this->fetchFilesByFormat($documentId));
    }

    public function testDocumentWithoutClassifiableArtifactsIsIgnored(): void
    {
        $primaryMedia = $this->createMedia(null);
        $documentId = $this->createDocument(DocumentType::INVOICE->value, $primaryMedia, null);

        (new Migration1787292621BackfillDocumentFiles())->update($this->connection);

        static::assertSame([], $this->fetchFilesByFormat($documentId));
    }

    public function testSameMediaInBothSlotsProducesASingleRow(): void
    {
        $media = $this->createMedia('pdf');
        $documentId = $this->createDocument(DocumentType::INVOICE->value, $media, $media);

        (new Migration1787292621BackfillDocumentFiles())->update($this->connection);

        static::assertSame([DocumentFormat::PDF->value => $this->hex($media)], $this->fetchFilesByFormat($documentId));
    }

    public function testTwoDocumentsSharingOneMediaDoNotCollide(): void
    {
        $sharedMedia = $this->createMedia('pdf');
        $this->createDocument(DocumentType::INVOICE->value, $sharedMedia, null);
        $this->createDocument(DocumentType::INVOICE->value, $sharedMedia, null);

        (new Migration1787292621BackfillDocumentFiles())->update($this->connection);

        $rowsForSharedMedia = $this->connection->fetchOne(
            'SELECT COUNT(*) FROM `document_file` WHERE `media_id` = :media',
            ['media' => $sharedMedia]
        );

        static::assertSame(1, (int) $rowsForSharedMedia);
    }

    public function testExistingDocumentFilesAreNeitherDuplicatedNorReplaced(): void
    {
        $primaryMedia = $this->createMedia('pdf');
        $existingMedia = $this->createMedia('pdf');
        $documentId = $this->createDocument(DocumentType::INVOICE->value, $primaryMedia, null);

        $this->connection->insert('document_file', [
            'id' => Uuid::randomBytes(),
            'document_id' => $documentId,
            'media_id' => $existingMedia,
            'document_format' => DocumentFormat::PDF->value,
            'created_at' => self::CREATED_AT,
        ]);

        (new Migration1787292621BackfillDocumentFiles())->update($this->connection);

        static::assertSame([DocumentFormat::PDF->value => $this->hex($existingMedia)], $this->fetchFilesByFormat($documentId));
    }

    public function testRunningTheMigrationTwiceIsIdempotent(): void
    {
        $primaryMedia = $this->createMedia('bin');
        $accessibilityMedia = $this->createMedia('html');
        $documentId = $this->createDocument('zugferd_embedded_invoice', $primaryMedia, $accessibilityMedia);

        (new Migration1787292621BackfillDocumentFiles())->update($this->connection);
        (new Migration1787292621BackfillDocumentFiles())->update($this->connection);

        static::assertSame(
            [
                DocumentFormat::HTML->value => $this->hex($accessibilityMedia),
                DocumentFormat::ZUGFERD_EMBEDDED_PDF->value => $this->hex($primaryMedia),
            ],
            $this->fetchFilesByFormat($documentId)
        );
        static::assertSame(DocumentType::INVOICE->value, $this->fetchTypeName($documentId));
    }

    public function testProcessesMultipleDocumentsInOneRun(): void
    {
        $invoiceMedia = $this->createMedia('pdf');
        $invoiceId = $this->createDocument(DocumentType::INVOICE->value, $invoiceMedia, null);

        $zugferdMedia = $this->createMedia('xml');
        $zugferdId = $this->createDocument('zugferd_invoice', $zugferdMedia, null);

        $creditMedia = $this->createMedia('html');
        $creditId = $this->createDocument(DocumentType::CREDIT_NOTE->value, null, $creditMedia);

        (new Migration1787292621BackfillDocumentFiles())->update($this->connection);

        static::assertSame([DocumentFormat::PDF->value => $this->hex($invoiceMedia)], $this->fetchFilesByFormat($invoiceId));
        static::assertSame([DocumentFormat::ZUGFERD_XML->value => $this->hex($zugferdMedia)], $this->fetchFilesByFormat($zugferdId));
        static::assertSame([DocumentFormat::HTML->value => $this->hex($creditMedia)], $this->fetchFilesByFormat($creditId));
        static::assertSame(DocumentType::INVOICE->value, $this->fetchTypeName($zugferdId));
    }

    /**
     * @return array<string, string>
     */
    private function fetchFilesByFormat(string $documentId): array
    {
        return $this->connection->fetchAllKeyValue(
            'SELECT `document_format`, LOWER(HEX(`media_id`)) FROM `document_file` WHERE `document_id` = :id ORDER BY `document_format`',
            ['id' => $documentId]
        );
    }

    private function fetchTypeName(string $documentId): string
    {
        $typeName = $this->connection->fetchOne('SELECT `type_name` FROM `document` WHERE `id` = :id', ['id' => $documentId]);
        static::assertIsString($typeName);

        return $typeName;
    }

    private function createMedia(?string $fileExtension): string
    {
        $id = Uuid::randomBytes();
        $this->connection->insert('media', [
            'id' => $id,
            'file_name' => 'test-' . Uuid::randomHex(),
            'file_extension' => $fileExtension,
            'created_at' => self::CREATED_AT,
        ]);

        return $id;
    }

    private function createDocument(string $typeName, ?string $primaryMediaId, ?string $accessibilityMediaId): string
    {
        $id = Uuid::randomBytes();
        $this->connection->insert('document', [
            'id' => $id,
            'order_id' => $this->orderId,
            'order_version_id' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
            'document_type_id' => $this->documentTypeId,
            'type_name' => $typeName,
            'document_media_file_id' => $primaryMediaId,
            'document_a11y_media_file_id' => $accessibilityMediaId,
            'config' => '{}',
            'deep_link_code' => Uuid::randomHex(),
            'sent' => 0,
            'static' => 0,
            'created_at' => self::CREATED_AT,
        ]);

        return $id;
    }

    private function fetchDocumentTypeId(): string
    {
        $id = $this->connection->fetchOne(
            'SELECT `id` FROM `document_type` WHERE `technical_name` = :name',
            ['name' => DocumentType::INVOICE->value]
        );
        static::assertIsString($id);

        return $id;
    }

    private function createOrder(): string
    {
        $orderId = Uuid::randomBytes();
        $this->connection->insert('`order`', [
            'id' => $orderId,
            'currency_factor' => 1.0,
            'order_date_time' => self::CREATED_AT,
            'version_id' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
            'price' => json_encode(['netPrice' => 100, 'taxStatus' => 'gross', 'totalPrice' => 100, 'positionPrice' => 1], \JSON_THROW_ON_ERROR),
            'currency_id' => Uuid::fromHexToBytes(Defaults::CURRENCY),
            'state_id' => $this->fetchOpenOrderStateId(),
            'language_id' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM),
            'sales_channel_id' => Uuid::fromHexToBytes(TestDefaults::SALES_CHANNEL),
            'billing_address_id' => Uuid::randomBytes(),
            'billing_address_version_id' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
            'shipping_costs' => '{}',
            'created_at' => self::CREATED_AT,
        ]);

        return $orderId;
    }

    private function fetchOpenOrderStateId(): string
    {
        $machineId = $this->connection->fetchOne(
            'SELECT `id` FROM `state_machine` WHERE `technical_name` = :name',
            ['name' => OrderStates::STATE_MACHINE]
        );
        static::assertIsString($machineId);

        $stateId = $this->connection->fetchOne(
            'SELECT `id` FROM `state_machine_state` WHERE `technical_name` = :state AND `state_machine_id` = :machineId',
            ['state' => OrderStates::STATE_OPEN, 'machineId' => $machineId]
        );
        static::assertIsString($stateId);

        return $stateId;
    }

    private function hex(string $binaryId): string
    {
        return strtolower(bin2hex($binaryId));
    }
}
