<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\DocumentV2\Generation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Document\DocumentEntity;
use Shopware\Core\Checkout\DocumentV2\Aggregate\DocumentFile\DocumentFileCollection;
use Shopware\Core\Checkout\DocumentV2\Aggregate\DocumentFile\DocumentFileEntity;
use Shopware\Core\Checkout\DocumentV2\DocumentFormat;
use Shopware\Core\Checkout\DocumentV2\Generation\DocumentArchiveGenerator;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Content\Media\MediaService;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
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
            $this->createDocumentFile($pdfMediaId, DocumentFormat::PDF->value, 'invoice', DocumentFormat::PDF->value, DocumentFormat::PDF->mimeType()),
            $this->createDocumentFile($htmlMediaId, DocumentFormat::HTML->value, 'invoice', DocumentFormat::HTML->value, DocumentFormat::HTML->mimeType()),
        ]));

        $mediaService = $this->createMock(MediaService::class);
        $mediaService->expects($this->exactly(2))
            ->method('loadFile')
            ->willReturnCallback(static fn (string $mediaId): string => match ($mediaId) {
                $pdfMediaId => 'pdf content',
                $htmlMediaId => 'html content',
                default => throw new \RuntimeException('Unexpected media id.'),
            });

        $archive = (new DocumentArchiveGenerator($mediaService, new Filesystem()))
            ->archive($document, Context::createDefaultContext());

        static::assertNotNull($archive);
        static::assertSame('1000.zip', $archive->getName());
        static::assertSame('application/zip', $archive->getContentType());

        $tempFile = tempnam(sys_get_temp_dir(), 'document-v2-test-');
        static::assertIsString($tempFile);
        static::assertNotFalse(file_put_contents($tempFile, $archive->getContent()));

        $zip = new \ZipArchive();
        static::assertTrue($zip->open($tempFile));
        static::assertSame('pdf content', $zip->getFromName('invoice-pdf.pdf'));
        static::assertSame('html content', $zip->getFromName('invoice-html.html'));
        $zip->close();

        (new Filesystem())->remove($tempFile);
    }

    public function testArchiveReturnsNullWithoutDocumentFiles(): void
    {
        $document = new DocumentEntity();
        $document->setId(Uuid::randomHex());
        $document->setDocumentFiles(new DocumentFileCollection());

        $archive = (new DocumentArchiveGenerator(static::createStub(MediaService::class), new Filesystem()))
            ->archive($document, Context::createDefaultContext());

        static::assertNull($archive);
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
