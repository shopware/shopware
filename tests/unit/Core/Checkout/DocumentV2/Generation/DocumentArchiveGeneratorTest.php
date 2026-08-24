<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\DocumentV2\Generation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Document\DocumentCollection;
use Shopware\Core\Checkout\Document\DocumentEntity;
use Shopware\Core\Checkout\Document\Renderer\RenderedDocument;
use Shopware\Core\Checkout\DocumentV2\Aggregate\DocumentFile\DocumentFileCollection;
use Shopware\Core\Checkout\DocumentV2\Aggregate\DocumentFile\DocumentFileEntity;
use Shopware\Core\Checkout\DocumentV2\DocumentFormat;
use Shopware\Core\Checkout\DocumentV2\DocumentV2Exception;
use Shopware\Core\Checkout\DocumentV2\Generation\DocumentArchiveGenerator;
use Shopware\Core\Checkout\DocumentV2\Renderer\DocumentRendererRegistry;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Content\Media\MediaService;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Tests\Unit\Core\Checkout\DocumentV2\Fixtures\StaticDocumentRenderer;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(DocumentArchiveGenerator::class)]
class DocumentArchiveGeneratorTest extends TestCase
{
    public function testArchiveContainsAllDocumentFiles(): void
    {
        $pdfMediaId = Uuid::randomHex();
        $htmlMediaId = Uuid::randomHex();

        $document = $this->createDocument('10000', '1000', [
            $this->createDocumentFile($pdfMediaId, DocumentFormat::PDF->value, 'invoice_1000_pdf', DocumentFormat::PDF->fileExtension(), DocumentFormat::PDF->mimeType()),
            $this->createDocumentFile($htmlMediaId, DocumentFormat::HTML->value, 'invoice_1000_html', DocumentFormat::HTML->fileExtension(), DocumentFormat::HTML->mimeType()),
        ]);

        $mediaService = $this->createMock(MediaService::class);
        $mediaService->expects($this->exactly(2))
            ->method('loadFile')
            ->willReturnCallback(static fn (string $mediaId): string => match ($mediaId) {
                $pdfMediaId => 'pdf content',
                $htmlMediaId => 'html content',
                default => throw new \RuntimeException('Unexpected media id.'),
            });

        $archive = $this->createArchiveGenerator($mediaService)
            ->archive(new DocumentCollection([$document]), Context::createDefaultContext());

        static::assertNotNull($archive);
        static::assertSame('1000.zip', $archive->getName());
        static::assertSame('application/zip', $archive->getContentType());

        $this->assertArchiveContains($archive, [
            '10000_invoice_1000_pdf.pdf' => 'pdf content',
            '10000_invoice_1000_html.html' => 'html content',
        ]);
    }

    public function testArchiveReturnsNullWithoutDocumentFiles(): void
    {
        $document = $this->createDocument('10000', '1000', []);

        $archive = $this->createArchiveGenerator(static::createStub(MediaService::class))
            ->archive(new DocumentCollection([$document]), Context::createDefaultContext());

        static::assertNull($archive);
    }

    public function testArchiveContainsLegacyDocumentFilesWhenNoV2FilesExist(): void
    {
        $pdfMediaId = Uuid::randomHex();

        $document = $this->createDocument('10000', '1000', []);
        $document->setDocumentMediaFile($this->createMedia(
            $pdfMediaId,
            'invoice_1000',
            DocumentFormat::PDF->fileExtension(),
            DocumentFormat::PDF->mimeType(),
        ));

        $mediaService = $this->createMock(MediaService::class);
        $mediaService->expects($this->once())
            ->method('loadFile')
            ->with($pdfMediaId, static::isInstanceOf(Context::class))
            ->willReturn('legacy pdf content');

        $archive = $this->createArchiveGenerator($mediaService)
            ->archive(new DocumentCollection([$document]), Context::createDefaultContext());

        static::assertNotNull($archive);

        $this->assertArchiveContains($archive, ['10000_invoice_1000.pdf' => 'legacy pdf content']);
    }

    public function testArchiveContainsLegacyExtendedDocumentFormat(): void
    {
        $xmlMediaId = Uuid::randomHex();

        $document = $this->createDocument('10000', '1000', []);
        $document->setDocumentMediaFile($this->createMedia(
            $xmlMediaId,
            'invoice_1000_zugferd',
            DocumentFormat::ZUGFERD_XML->fileExtension(),
            DocumentFormat::ZUGFERD_XML->mimeType(),
        ));

        $mediaService = $this->createMock(MediaService::class);
        $mediaService->expects($this->once())
            ->method('loadFile')
            ->with($xmlMediaId, static::isInstanceOf(Context::class))
            ->willReturn('legacy zugferd xml content');

        $archive = $this->createArchiveGenerator($mediaService)
            ->archive(new DocumentCollection([$document]), Context::createDefaultContext());

        static::assertNotNull($archive);

        $this->assertArchiveContains($archive, ['10000_invoice_1000_zugferd.xml' => 'legacy zugferd xml content']);
    }

    public function testArchivePrefersV2FileWhenLegacyFileHasTheSameName(): void
    {
        $v2MediaId = Uuid::randomHex();
        $legacyMediaId = Uuid::randomHex();

        $document = $this->createDocument('10000', '1000', [
            $this->createDocumentFile($v2MediaId, DocumentFormat::PDF->value, 'invoice_1000', DocumentFormat::PDF->fileExtension(), DocumentFormat::PDF->mimeType()),
        ]);
        $document->setDocumentMediaFile($this->createMedia(
            $legacyMediaId,
            'invoice_1000',
            DocumentFormat::PDF->fileExtension(),
            DocumentFormat::PDF->mimeType(),
        ));

        $mediaService = $this->createMock(MediaService::class);
        $mediaService->expects($this->once())
            ->method('loadFile')
            ->with($v2MediaId, static::isInstanceOf(Context::class))
            ->willReturn('v2 pdf content');

        $archive = $this->createArchiveGenerator($mediaService)
            ->archive(new DocumentCollection([$document]), Context::createDefaultContext());

        static::assertNotNull($archive);

        $this->assertArchiveContains($archive, ['10000_invoice_1000.pdf' => 'v2 pdf content']);
    }

    public function testArchiveMergesV2AndLegacyFilesForEveryDocument(): void
    {
        $firstV2MediaId = Uuid::randomHex();
        $firstLegacyMediaId = Uuid::randomHex();
        $secondLegacyMediaId = Uuid::randomHex();

        // The first order was migrated to v2 but kept its accessible html from v1, the second one
        // was never migrated at all. Both must end up in the same archive.
        $firstDocument = $this->createDocument('10000', '1000', [
            $this->createDocumentFile($firstV2MediaId, DocumentFormat::PDF->value, 'invoice_1000', DocumentFormat::PDF->fileExtension(), DocumentFormat::PDF->mimeType()),
        ]);
        $firstDocument->setDocumentA11yMediaFile($this->createMedia(
            $firstLegacyMediaId,
            'invoice_1000_a11y',
            DocumentFormat::HTML->fileExtension(),
            DocumentFormat::HTML->mimeType(),
        ));

        $secondDocument = $this->createDocument('10001', '1001', []);
        $secondDocument->setDocumentMediaFile($this->createMedia(
            $secondLegacyMediaId,
            'invoice_1001',
            DocumentFormat::PDF->fileExtension(),
            DocumentFormat::PDF->mimeType(),
        ));

        $mediaService = $this->createMock(MediaService::class);
        $mediaService->expects($this->exactly(3))
            ->method('loadFile')
            ->willReturnCallback(static fn (string $mediaId): string => match ($mediaId) {
                $firstV2MediaId => 'first v2 pdf content',
                $firstLegacyMediaId => 'first legacy a11y content',
                $secondLegacyMediaId => 'second legacy pdf content',
                default => throw new \RuntimeException('Unexpected media id.'),
            });

        $archive = $this->createArchiveGenerator($mediaService)
            ->archive(new DocumentCollection([$firstDocument, $secondDocument]), Context::createDefaultContext());

        static::assertNotNull($archive);
        static::assertSame('documents.zip', $archive->getName());

        $this->assertArchiveContains($archive, [
            '10000_invoice_1000.pdf' => 'first v2 pdf content',
            '10000_invoice_1000_a11y.html' => 'first legacy a11y content',
            '10001_invoice_1001.pdf' => 'second legacy pdf content',
        ]);
    }

    public function testArchiveContainsFilesFromMultipleDocumentsWithoutNameCollisions(): void
    {
        $firstInvoiceMediaId = Uuid::randomHex();
        $secondInvoiceMediaId = Uuid::randomHex();

        // Two different orders can legitimately produce the same document number (e.g. separate
        // sales channels with independent number ranges), so the same file name can occur twice.
        // The archive must still tell them apart by order number.
        $firstInvoice = $this->createDocument('10000', '1000', [
            $this->createDocumentFile($firstInvoiceMediaId, DocumentFormat::PDF->value, 'invoice_1000', DocumentFormat::PDF->fileExtension(), DocumentFormat::PDF->mimeType()),
        ]);
        $secondInvoice = $this->createDocument('10001', '1000', [
            $this->createDocumentFile($secondInvoiceMediaId, DocumentFormat::PDF->value, 'invoice_1000', DocumentFormat::PDF->fileExtension(), DocumentFormat::PDF->mimeType()),
        ]);

        $mediaService = $this->createMock(MediaService::class);
        $mediaService->expects($this->exactly(2))
            ->method('loadFile')
            ->willReturnCallback(static fn (string $mediaId): string => match ($mediaId) {
                $firstInvoiceMediaId => 'first invoice content',
                $secondInvoiceMediaId => 'second invoice content',
                default => throw new \RuntimeException('Unexpected media id.'),
            });

        $archive = $this->createArchiveGenerator($mediaService)
            ->archive(new DocumentCollection([$firstInvoice, $secondInvoice]), Context::createDefaultContext());

        static::assertNotNull($archive);
        static::assertSame('documents.zip', $archive->getName());

        $this->assertArchiveContains($archive, [
            '10000_invoice_1000.pdf' => 'first invoice content',
            '10001_invoice_1000.pdf' => 'second invoice content',
        ]);
    }

    public function testArchiveFallsBackToOrderIdWhenOrderAssociationIsMissing(): void
    {
        $mediaId = Uuid::randomHex();
        $orderId = Uuid::randomHex();

        $document = new DocumentEntity();
        $document->setId(Uuid::randomHex());
        $document->setOrderId($orderId);
        $document->setConfig(['documentNumber' => '1000']);
        $document->setDocumentFiles(new DocumentFileCollection([
            $this->createDocumentFile($mediaId, DocumentFormat::PDF->value, 'document_1000', DocumentFormat::PDF->fileExtension(), DocumentFormat::PDF->mimeType()),
        ]));

        $mediaService = $this->createMock(MediaService::class);
        $mediaService->expects($this->once())->method('loadFile')->willReturn('pdf content');

        $archive = $this->createArchiveGenerator($mediaService)
            ->archive(new DocumentCollection([$document]), Context::createDefaultContext());

        static::assertNotNull($archive);

        $this->assertArchiveContains($archive, [
            \sprintf('%s_document_1000.pdf', $orderId) => 'pdf content',
        ]);
    }

    public function testArchiveFallsBackToDocumentFormatExtensionWhenMediaHasNoFileExtension(): void
    {
        $mediaId = Uuid::randomHex();
        $media = new MediaEntity();
        $media->setId($mediaId);
        $media->setFileName('invoice_1000_custom');
        $media->setMimeType('application/custom');

        $documentFile = new DocumentFileEntity();
        $documentFile->setId(Uuid::randomHex());
        $documentFile->setDocumentId(Uuid::randomHex());
        $documentFile->setDocumentFormat('custom_format');
        $documentFile->setMediaId($media->getId());
        $documentFile->setMedia($media);

        $document = $this->createDocument('10000', '1000', [$documentFile]);

        $mediaService = $this->createMock(MediaService::class);
        $mediaService->expects($this->once())
            ->method('loadFile')
            ->with($mediaId, static::isInstanceOf(Context::class))
            ->willReturn('custom content');

        $archive = $this->createArchiveGenerator($mediaService)
            ->archive(new DocumentCollection([$document]), Context::createDefaultContext());

        static::assertNotNull($archive);

        $this->assertArchiveContains($archive, ['10000_invoice_1000_custom.custom' => 'custom content']);
    }

    public function testArchiveThrowsWhenFileExtensionIsUnavailable(): void
    {
        $mediaId = Uuid::randomHex();
        $format = 'unknown_format';

        $media = new MediaEntity();
        $media->setId($mediaId);
        $media->setFileName('invoice_1000_unknown');

        $documentFile = new DocumentFileEntity();
        $documentFile->setId(Uuid::randomHex());
        $documentFile->setDocumentId(Uuid::randomHex());
        $documentFile->setDocumentFormat($format);
        $documentFile->setMediaId($mediaId);
        $documentFile->setMedia($media);

        $document = $this->createDocument('10000', '1000', [$documentFile]);

        $mediaService = $this->createMock(MediaService::class);
        $mediaService->expects($this->never())->method('loadFile');

        static::expectExceptionObject(DocumentV2Exception::documentFileExtensionUnavailable($document->getId(), $format));

        $this->createArchiveGenerator($mediaService)
            ->archive(new DocumentCollection([$document]), Context::createDefaultContext());
    }

    private function createArchiveGenerator(MediaService $mediaService): DocumentArchiveGenerator
    {
        return new DocumentArchiveGenerator(
            $mediaService,
            new Filesystem(),
            new DocumentRendererRegistry([
                new StaticDocumentRenderer(DocumentFormat::PDF),
                new StaticDocumentRenderer(DocumentFormat::HTML),
                new StaticDocumentRenderer('custom_format', fileExtension: 'custom'),
            ]),
        );
    }

    /**
     * @param array<string, string> $expectedFiles
     */
    private function assertArchiveContains(RenderedDocument $archive, array $expectedFiles): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'document-v2-test-');
        static::assertIsString($tempFile);

        $filesystem = new Filesystem();
        try {
            $filesystem->dumpFile($tempFile, $archive->getContent());

            $zip = new \ZipArchive();
            static::assertTrue($zip->open($tempFile));
            static::assertSame(\count($expectedFiles), $zip->numFiles);

            foreach ($expectedFiles as $fileName => $content) {
                static::assertSame($content, $zip->getFromName($fileName));
            }

            $zip->close();
        } finally {
            $filesystem->remove($tempFile);
        }
    }

    /**
     * @param list<DocumentFileEntity> $documentFiles
     */
    private function createDocument(string $orderNumber, string $documentNumber, array $documentFiles): DocumentEntity
    {
        $order = new OrderEntity();
        $order->setId(Uuid::randomHex());
        $order->setOrderNumber($orderNumber);

        $document = new DocumentEntity();
        $document->setId(Uuid::randomHex());
        $document->setOrderId($order->getId());
        $document->setOrder($order);
        $document->setConfig(['documentNumber' => $documentNumber]);
        $document->setDocumentFiles(new DocumentFileCollection($documentFiles));

        return $document;
    }

    private function createDocumentFile(
        string $mediaId,
        string $format,
        string $fileName,
        string $fileExtension,
        string $mimeType,
    ): DocumentFileEntity {
        $documentFile = new DocumentFileEntity();
        $documentFile->setId(Uuid::randomHex());
        $documentFile->setDocumentId(Uuid::randomHex());
        $documentFile->setDocumentFormat($format);
        $documentFile->setMediaId($mediaId);
        $documentFile->setMedia($this->createMedia($mediaId, $fileName, $fileExtension, $mimeType));

        return $documentFile;
    }

    private function createMedia(string $mediaId, string $fileName, string $fileExtension, string $mimeType): MediaEntity
    {
        $media = new MediaEntity();
        $media->setId($mediaId);
        $media->setFileName($fileName);
        $media->setFileExtension($fileExtension);
        $media->setMimeType($mimeType);

        return $media;
    }
}
