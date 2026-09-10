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
        $document = new DocumentEntity();
        $document->setId(Uuid::randomHex());
        $document->setConfig(['documentNumber' => '1000']);
        $document->setDocumentFiles(new DocumentFileCollection([
            $this->createDocumentFile($pdfMediaId, DocumentFormat::PDF->value, 'invoice_1000_pdf', DocumentFormat::PDF->fileExtension(), DocumentFormat::PDF->mimeType()),
            $this->createDocumentFile($htmlMediaId, DocumentFormat::HTML->value, 'invoice_1000_html', DocumentFormat::HTML->fileExtension(), DocumentFormat::HTML->mimeType()),
        ]));

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
            'invoice_1000_pdf.pdf' => 'pdf content',
            'invoice_1000_html.html' => 'html content',
        ]);
    }

    public function testArchiveReturnsNullWithoutDocumentFiles(): void
    {
        $document = new DocumentEntity();
        $document->setId(Uuid::randomHex());
        $document->setDocumentFiles(new DocumentFileCollection());

        $archive = $this->createArchiveGenerator(static::createStub(MediaService::class))
            ->archive(new DocumentCollection([$document]), Context::createDefaultContext());

        static::assertNull($archive);
    }

    public function testArchiveContainsLegacyDocumentFilesWhenNoV2FilesExist(): void
    {
        $pdfMediaId = Uuid::randomHex();
        $pdfMedia = new MediaEntity();
        $pdfMedia->setId($pdfMediaId);
        $pdfMedia->setFileName('invoice_1000');
        $pdfMedia->setFileExtension(DocumentFormat::PDF->fileExtension());
        $pdfMedia->setMimeType(DocumentFormat::PDF->mimeType());

        $document = new DocumentEntity();
        $document->setId(Uuid::randomHex());
        $document->setConfig(['documentNumber' => '1000']);
        $document->setDocumentMediaFile($pdfMedia);

        $mediaService = $this->createMock(MediaService::class);
        $mediaService->expects($this->once())
            ->method('loadFile')
            ->with($pdfMediaId, static::isInstanceOf(Context::class))
            ->willReturn('legacy pdf content');

        $archive = $this->createArchiveGenerator($mediaService)
            ->archive(new DocumentCollection([$document]), Context::createDefaultContext());

        static::assertNotNull($archive);

        $this->assertArchiveContains($archive, ['invoice_1000.pdf' => 'legacy pdf content']);
    }

    public function testArchiveContainsLegacyExtendedDocumentFormat(): void
    {
        $xmlMediaId = Uuid::randomHex();
        $xmlMedia = new MediaEntity();
        $xmlMedia->setId($xmlMediaId);
        $xmlMedia->setFileName('invoice_1000_zugferd');
        $xmlMedia->setFileExtension(DocumentFormat::ZUGFERD_XML->fileExtension());
        $xmlMedia->setMimeType(DocumentFormat::ZUGFERD_XML->mimeType());

        $document = new DocumentEntity();
        $document->setId(Uuid::randomHex());
        $document->setConfig(['documentNumber' => '1000']);
        $document->setDocumentMediaFile($xmlMedia);

        $mediaService = $this->createMock(MediaService::class);
        $mediaService->expects($this->once())
            ->method('loadFile')
            ->with($xmlMediaId, static::isInstanceOf(Context::class))
            ->willReturn('legacy zugferd xml content');

        $archive = $this->createArchiveGenerator($mediaService)
            ->archive(new DocumentCollection([$document]), Context::createDefaultContext());

        static::assertNotNull($archive);

        $this->assertArchiveContains($archive, ['invoice_1000_zugferd.xml' => 'legacy zugferd xml content']);
    }

    public function testArchivePrefersV2FileWhenLegacyFileHasTheSameName(): void
    {
        $v2MediaId = Uuid::randomHex();
        $legacyMediaId = Uuid::randomHex();
        $v2Media = new MediaEntity();
        $v2Media->setId($v2MediaId);
        $v2Media->setFileName('invoice_1000');
        $v2Media->setFileExtension(DocumentFormat::PDF->fileExtension());
        $v2Media->setMimeType(DocumentFormat::PDF->mimeType());

        $legacyMedia = new MediaEntity();
        $legacyMedia->setId($legacyMediaId);
        $legacyMedia->setFileName('invoice_1000');
        $legacyMedia->setFileExtension(DocumentFormat::PDF->fileExtension());
        $legacyMedia->setMimeType(DocumentFormat::PDF->mimeType());

        $document = new DocumentEntity();
        $document->setId(Uuid::randomHex());
        $document->setConfig(['documentNumber' => '1000']);
        $document->setDocumentFiles(new DocumentFileCollection([
            $this->createDocumentFile(
                $v2MediaId,
                DocumentFormat::PDF->value,
                'invoice_1000',
                DocumentFormat::PDF->fileExtension(),
                DocumentFormat::PDF->mimeType(),
            ),
        ]));
        $document->setDocumentMediaFile($legacyMedia);

        $mediaService = $this->createMock(MediaService::class);
        $mediaService->expects($this->once())
            ->method('loadFile')
            ->with($v2MediaId, static::isInstanceOf(Context::class))
            ->willReturn('v2 pdf content');

        $archive = $this->createArchiveGenerator($mediaService)
            ->archive(new DocumentCollection([$document]), Context::createDefaultContext());

        static::assertNotNull($archive);

        $this->assertArchiveContains($archive, ['invoice_1000.pdf' => 'v2 pdf content']);
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

        $document = new DocumentEntity();
        $document->setId(Uuid::randomHex());
        $document->setConfig(['documentNumber' => '1000']);
        $document->setDocumentFiles(new DocumentFileCollection([$documentFile]));

        $mediaService = $this->createMock(MediaService::class);
        $mediaService->expects($this->once())
            ->method('loadFile')
            ->with($mediaId, static::isInstanceOf(Context::class))
            ->willReturn('custom content');

        $archive = $this->createArchiveGenerator($mediaService)
            ->archive(new DocumentCollection([$document]), Context::createDefaultContext());

        static::assertNotNull($archive);

        $this->assertArchiveContains($archive, ['invoice_1000_custom.custom' => 'custom content']);
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

        $document = new DocumentEntity();
        $document->setId(Uuid::randomHex());
        $document->setConfig(['documentNumber' => '1000']);
        $document->setDocumentFiles(new DocumentFileCollection([$documentFile]));

        $mediaService = $this->createMock(MediaService::class);
        $mediaService->expects($this->never())->method('loadFile');

        static::expectExceptionObject(DocumentV2Exception::documentFileExtensionUnavailable($document->getId(), $format));

        $this->createArchiveGenerator($mediaService)
            ->archive(new DocumentCollection([$document]), Context::createDefaultContext());
    }

    public function testArchiveContainsFilesFromMultipleDocumentsWithoutNameCollisions(): void
    {
        $firstInvoiceMediaId = Uuid::randomHex();
        $secondInvoiceMediaId = Uuid::randomHex();

        // Two different orders can legitimately produce the same document number (e.g. separate
        // sales channels with independent number ranges), so the same file name can occur twice.
        // The archive must still tell them apart by order number.
        $firstOrder = new OrderEntity();
        $firstOrder->setId(Uuid::randomHex());
        $firstOrder->setOrderNumber('10000');

        $firstInvoice = new DocumentEntity();
        $firstInvoice->setId(Uuid::randomHex());
        $firstInvoice->setOrderId($firstOrder->getId());
        $firstInvoice->setOrder($firstOrder);
        $firstInvoice->setConfig(['documentNumber' => '1000']);
        $firstInvoice->setDocumentFiles(new DocumentFileCollection([
            $this->createDocumentFile($firstInvoiceMediaId, DocumentFormat::PDF->value, 'invoice_1000', DocumentFormat::PDF->fileExtension(), DocumentFormat::PDF->mimeType()),
        ]));

        $secondOrder = new OrderEntity();
        $secondOrder->setId(Uuid::randomHex());
        $secondOrder->setOrderNumber('10001');

        $secondInvoice = new DocumentEntity();
        $secondInvoice->setId(Uuid::randomHex());
        $secondInvoice->setOrderId($secondOrder->getId());
        $secondInvoice->setOrder($secondOrder);
        $secondInvoice->setConfig(['documentNumber' => '1000']);
        $secondInvoice->setDocumentFiles(new DocumentFileCollection([
            $this->createDocumentFile($secondInvoiceMediaId, DocumentFormat::PDF->value, 'invoice_1000', DocumentFormat::PDF->fileExtension(), DocumentFormat::PDF->mimeType()),
        ]));

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
        $firstMediaId = Uuid::randomHex();
        $secondMediaId = Uuid::randomHex();
        $firstOrderId = Uuid::randomHex();
        $secondOrderId = Uuid::randomHex();

        // Neither document has its order loaded, so the archive can only tell them apart by order id.
        $firstInvoice = new DocumentEntity();
        $firstInvoice->setId(Uuid::randomHex());
        $firstInvoice->setOrderId($firstOrderId);
        $firstInvoice->setConfig(['documentNumber' => '1000']);
        $firstInvoice->setDocumentFiles(new DocumentFileCollection([
            $this->createDocumentFile($firstMediaId, DocumentFormat::PDF->value, 'document_1000', DocumentFormat::PDF->fileExtension(), DocumentFormat::PDF->mimeType()),
        ]));

        $secondInvoice = new DocumentEntity();
        $secondInvoice->setId(Uuid::randomHex());
        $secondInvoice->setOrderId($secondOrderId);
        $secondInvoice->setConfig(['documentNumber' => '1000']);
        $secondInvoice->setDocumentFiles(new DocumentFileCollection([
            $this->createDocumentFile($secondMediaId, DocumentFormat::PDF->value, 'document_1000', DocumentFormat::PDF->fileExtension(), DocumentFormat::PDF->mimeType()),
        ]));

        $mediaService = $this->createMock(MediaService::class);
        $mediaService->expects($this->exactly(2))
            ->method('loadFile')
            ->willReturnCallback(static fn (string $mediaId): string => match ($mediaId) {
                $firstMediaId => 'first invoice content',
                $secondMediaId => 'second invoice content',
                default => throw new \RuntimeException('Unexpected media id.'),
            });

        $archive = $this->createArchiveGenerator($mediaService)
            ->archive(new DocumentCollection([$firstInvoice, $secondInvoice]), Context::createDefaultContext());

        static::assertNotNull($archive);

        $this->assertArchiveContains($archive, [
            \sprintf('%s_document_1000.pdf', $firstOrderId) => 'first invoice content',
            \sprintf('%s_document_1000.pdf', $secondOrderId) => 'second invoice content',
        ]);
    }

    public function testArchiveFallsBackToDocumentIdForOrderlessDocuments(): void
    {
        $firstMediaId = Uuid::randomHex();
        $secondMediaId = Uuid::randomHex();

        $firstDocument = new DocumentEntity();
        $firstDocument->setId(Uuid::randomHex());
        $firstDocument->setConfig(['documentNumber' => '1000']);
        $firstDocument->setDocumentFiles(new DocumentFileCollection([
            $this->createDocumentFile($firstMediaId, DocumentFormat::PDF->value, 'document_1000', DocumentFormat::PDF->fileExtension(), DocumentFormat::PDF->mimeType()),
        ]));

        $secondDocument = new DocumentEntity();
        $secondDocument->setId(Uuid::randomHex());
        $secondDocument->setConfig(['documentNumber' => '1000']);
        $secondDocument->setDocumentFiles(new DocumentFileCollection([
            $this->createDocumentFile($secondMediaId, DocumentFormat::PDF->value, 'document_1000', DocumentFormat::PDF->fileExtension(), DocumentFormat::PDF->mimeType()),
        ]));

        $mediaService = $this->createMock(MediaService::class);
        $mediaService->expects($this->exactly(2))
            ->method('loadFile')
            ->willReturnCallback(static fn (string $mediaId): string => match ($mediaId) {
                $firstMediaId => 'first document content',
                $secondMediaId => 'second document content',
                default => throw new \RuntimeException('Unexpected media id.'),
            });

        $archive = $this->createArchiveGenerator($mediaService)
            ->archive(new DocumentCollection([$firstDocument, $secondDocument]), Context::createDefaultContext());

        static::assertNotNull($archive);

        $this->assertArchiveContains($archive, [
            \sprintf('%s_document_1000.pdf', $firstDocument->getId()) => 'first document content',
            \sprintf('%s_document_1000.pdf', $secondDocument->getId()) => 'second document content',
        ]);
    }

    public function testArchiveDoesNotPrefixEntriesWhenAllDocumentsBelongToTheSameOrder(): void
    {
        $invoiceMediaId = Uuid::randomHex();
        $deliveryNoteMediaId = Uuid::randomHex();
        $orderId = Uuid::randomHex();

        $invoice = new DocumentEntity();
        $invoice->setId(Uuid::randomHex());
        $invoice->setOrderId($orderId);
        $invoice->setConfig(['documentNumber' => '1000']);
        $invoice->setDocumentFiles(new DocumentFileCollection([
            $this->createDocumentFile($invoiceMediaId, DocumentFormat::PDF->value, 'invoice_1000', DocumentFormat::PDF->fileExtension(), DocumentFormat::PDF->mimeType()),
        ]));

        $deliveryNote = new DocumentEntity();
        $deliveryNote->setId(Uuid::randomHex());
        $deliveryNote->setOrderId($orderId);
        $deliveryNote->setConfig(['documentNumber' => '2000']);
        $deliveryNote->setDocumentFiles(new DocumentFileCollection([
            $this->createDocumentFile($deliveryNoteMediaId, DocumentFormat::PDF->value, 'delivery_note_2000', DocumentFormat::PDF->fileExtension(), DocumentFormat::PDF->mimeType()),
        ]));

        $mediaService = $this->createMock(MediaService::class);
        $mediaService->expects($this->exactly(2))
            ->method('loadFile')
            ->willReturnCallback(static fn (string $mediaId): string => match ($mediaId) {
                $invoiceMediaId => 'invoice content',
                $deliveryNoteMediaId => 'delivery note content',
                default => throw new \RuntimeException('Unexpected media id.'),
            });

        $archive = $this->createArchiveGenerator($mediaService)
            ->archive(new DocumentCollection([$invoice, $deliveryNote]), Context::createDefaultContext());

        static::assertNotNull($archive);

        $this->assertArchiveContains($archive, [
            'invoice_1000.pdf' => 'invoice content',
            'delivery_note_2000.pdf' => 'delivery note content',
        ]);
    }

    public function testArchiveThrowsWhenMoreDocumentsThanTheLimitAreRequested(): void
    {
        $documents = new DocumentCollection();
        for ($i = 0; $i <= DocumentArchiveGenerator::MAX_DOCUMENTS; ++$i) {
            $document = new DocumentEntity();
            $document->setId(Uuid::randomHex());
            $document->setConfig(['documentNumber' => (string) $i]);
            $document->setDocumentFiles(new DocumentFileCollection());
            $documents->add($document);
        }

        $mediaService = $this->createMock(MediaService::class);
        $mediaService->expects($this->never())->method('loadFile');

        static::expectExceptionObject(DocumentV2Exception::documentArchiveLimitExceeded(
            DocumentArchiveGenerator::MAX_DOCUMENTS + 1,
            DocumentArchiveGenerator::MAX_DOCUMENTS,
        ));

        $this->createArchiveGenerator($mediaService)->archive($documents, Context::createDefaultContext());
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

    private function createDocumentFile(
        string $mediaId,
        string $format,
        string $fileName,
        string $fileExtension,
        string $mimeType,
    ): DocumentFileEntity {
        $media = new MediaEntity();
        $media->setId($mediaId);
        $media->setFileName($fileName);
        $media->setFileExtension($fileExtension);
        $media->setMimeType($mimeType);

        $documentFile = new DocumentFileEntity();
        $documentFile->setId(Uuid::randomHex());
        $documentFile->setDocumentId(Uuid::randomHex());
        $documentFile->setDocumentFormat($format);
        $documentFile->setMediaId($mediaId);
        $documentFile->setMedia($media);

        return $documentFile;
    }
}
