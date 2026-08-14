<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\DocumentV2\Generation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Document\Aggregate\DocumentType\DocumentTypeEntity;
use Shopware\Core\Checkout\Document\DocumentCollection;
use Shopware\Core\Checkout\Document\DocumentEntity;
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
    private string $tempFile = '';

    public function testArchiveContainsAllFormatsOfASingleDocument(): void
    {
        $pdfMediaId = Uuid::randomHex();
        $htmlMediaId = Uuid::randomHex();
        $document = $this->createDocument('10000', 'invoice', '1000', [
            $this->createDocumentFile($pdfMediaId, DocumentFormat::PDF->value, DocumentFormat::PDF->fileExtension(), DocumentFormat::PDF->mimeType(), 'invoice_1000'),
            $this->createDocumentFile($htmlMediaId, DocumentFormat::HTML->value, DocumentFormat::HTML->fileExtension(), DocumentFormat::HTML->mimeType(), 'invoice_1000'),
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

        $zip = $this->openZip($archive->getContent());
        static::assertSame('pdf content', $zip->getFromName('10000_invoice_1000.pdf'));
        static::assertSame('html content', $zip->getFromName('10000_invoice_1000.html'));
        $this->closeZip($zip);
    }

    public function testArchiveContainsFilesFromMultipleDocumentsWithoutNameCollisions(): void
    {
        $firstInvoiceMediaId = Uuid::randomHex();
        $secondInvoiceMediaId = Uuid::randomHex();

        // Two different orders can legitimately produce the same document number (e.g. separate
        // sales channels with independent number ranges), so the same file name can occur twice.
        // The archive must still tell them apart by order number.
        $firstInvoice = $this->createDocument('10000', 'invoice', '1000', [
            $this->createDocumentFile($firstInvoiceMediaId, DocumentFormat::PDF->value, DocumentFormat::PDF->fileExtension(), DocumentFormat::PDF->mimeType(), 'invoice_1000'),
        ]);
        $secondInvoice = $this->createDocument('10001', 'invoice', '1000', [
            $this->createDocumentFile($secondInvoiceMediaId, DocumentFormat::PDF->value, DocumentFormat::PDF->fileExtension(), DocumentFormat::PDF->mimeType(), 'invoice_1000'),
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

        $zip = $this->openZip($archive->getContent());
        static::assertSame(2, $zip->numFiles);
        static::assertSame('first invoice content', $zip->getFromName('10000_invoice_1000.pdf'));
        static::assertSame('second invoice content', $zip->getFromName('10001_invoice_1000.pdf'));
        $this->closeZip($zip);
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
            $this->createDocumentFile($mediaId, DocumentFormat::PDF->value, DocumentFormat::PDF->fileExtension(), DocumentFormat::PDF->mimeType(), 'document_1000'),
        ]));

        $mediaService = $this->createMock(MediaService::class);
        $mediaService->expects($this->once())->method('loadFile')->willReturn('pdf content');

        $archive = $this->createArchiveGenerator($mediaService)
            ->archive(new DocumentCollection([$document]), Context::createDefaultContext());

        static::assertNotNull($archive);

        $zip = $this->openZip($archive->getContent());
        static::assertSame('pdf content', $zip->getFromName(\sprintf('%s_document_1000.pdf', $orderId)));
        $this->closeZip($zip);
    }

    public function testArchiveReturnsNullWithoutDocumentFiles(): void
    {
        $document = $this->createDocument('10000', 'invoice', '1000', []);

        $archive = $this->createArchiveGenerator(static::createStub(MediaService::class))
            ->archive(new DocumentCollection([$document]), Context::createDefaultContext());

        static::assertNull($archive);
    }

    public function testArchiveFallsBackToDocumentFormatExtensionWhenMediaHasNoFileExtension(): void
    {
        $mediaId = Uuid::randomHex();
        $media = new MediaEntity();
        $media->setId($mediaId);
        $media->setMimeType('application/custom');
        $media->setFileName('invoice_1000');

        $documentFile = new DocumentFileEntity();
        $documentFile->setId(Uuid::randomHex());
        $documentFile->setDocumentId(Uuid::randomHex());
        $documentFile->setDocumentFormat('custom_format');
        $documentFile->setMediaId($media->getId());
        $documentFile->setMedia($media);

        $document = $this->createDocument('10000', 'invoice', '1000', [$documentFile]);

        $mediaService = $this->createMock(MediaService::class);
        $mediaService->expects($this->once())
            ->method('loadFile')
            ->with($mediaId, static::isInstanceOf(Context::class))
            ->willReturn('custom content');

        $archive = $this->createArchiveGenerator($mediaService)
            ->archive(new DocumentCollection([$document]), Context::createDefaultContext());

        static::assertNotNull($archive);

        $zip = $this->openZip($archive->getContent());
        static::assertSame('custom content', $zip->getFromName('10000_invoice_1000.custom'));
        $this->closeZip($zip);
    }

    public function testArchiveThrowsWhenFileExtensionIsUnavailable(): void
    {
        $mediaId = Uuid::randomHex();
        $format = 'unknown_format';

        $media = new MediaEntity();
        $media->setId($mediaId);

        $documentFile = new DocumentFileEntity();
        $documentFile->setId(Uuid::randomHex());
        $documentFile->setDocumentId(Uuid::randomHex());
        $documentFile->setDocumentFormat($format);
        $documentFile->setMediaId($mediaId);
        $documentFile->setMedia($media);

        $document = $this->createDocument('10000', 'invoice', '1000', [$documentFile]);

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
     * @param list<DocumentFileEntity> $documentFiles
     */
    private function createDocument(string $orderNumber, string $documentTypeTechnicalName, string $documentNumber, array $documentFiles): DocumentEntity
    {
        $order = new OrderEntity();
        $order->setId(Uuid::randomHex());
        $order->setOrderNumber($orderNumber);

        $documentType = new DocumentTypeEntity();
        $documentType->setId(Uuid::randomHex());
        $documentType->setTechnicalName($documentTypeTechnicalName);

        $document = new DocumentEntity();
        $document->setId(Uuid::randomHex());
        $document->setOrderId($order->getId());
        $document->setOrder($order);
        $document->setDocumentType($documentType);
        $document->setConfig(['documentNumber' => $documentNumber]);
        $document->setDocumentFiles(new DocumentFileCollection($documentFiles));

        return $document;
    }

    private function createDocumentFile(
        string $mediaId,
        string $format,
        string $fileExtension,
        string $mimeType,
        string $fileName,
    ): DocumentFileEntity {
        $media = new MediaEntity();
        $media->setId($mediaId);
        $media->setFileExtension($fileExtension);
        $media->setMimeType($mimeType);
        $media->setFileName($fileName);

        $documentFile = new DocumentFileEntity();
        $documentFile->setId(Uuid::randomHex());
        $documentFile->setDocumentId(Uuid::randomHex());
        $documentFile->setDocumentFormat($format);
        $documentFile->setMediaId($mediaId);
        $documentFile->setMedia($media);

        return $documentFile;
    }

    private function openZip(string $content): \ZipArchive
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'document-v2-test-');
        static::assertIsString($tempFile);
        static::assertNotFalse(file_put_contents($tempFile, $content));

        $zip = new \ZipArchive();
        static::assertTrue($zip->open($tempFile));

        $this->tempFile = $tempFile;

        return $zip;
    }

    private function closeZip(\ZipArchive $zip): void
    {
        $zip->close();
        (new Filesystem())->remove($this->tempFile);
    }
}
